import sys
import os

# ==========================================
# 0. インポートパスの解決
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(os.path.dirname(current_dir)) # scraperディレクトリの親(project root)まで遡る可能性を考慮
scraper_dir = os.path.dirname(current_dir) # scraperディレクトリ

# パスを通す
if scraper_dir not in sys.path:
    sys.path.append(scraper_dir)

import scrapy
from scrapy.crawler import CrawlerProcess
import re
import logging
from sqlalchemy.orm import Session

# 共通基盤のインポート
from common.database import SessionLocal, Listing, Shop, ShopIdentifier, Site
from utils import normalize_prefecture

class GooBikeShopFixSpider(scrapy.Spider):
    """
    GooBikeのリスティングで shop_id が NULL のものを対象に
    詳細ページを巡回し、ショップIDを特定して紐付けを行うスパイダー。
    DB制約（住所必須など）に対応するため、住所情報も取得して保存します。
    """
    name = "goobike_shop_fix"
    site_name = "GooBike"
    allowed_domains = ["www.goobike.com"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 0.5,
        'RANDOMIZE_DOWNLOAD_DELAY': True,
        'COOKIES_ENABLED': True,
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'ITEM_PIPELINES': {}, 
    }

    def start_requests(self):
        self.db = SessionLocal()
        try:
            # サイトIDの取得
            site = self.db.query(Site).filter(Site.name == 'GooBike').first()
            if not site:
                site = self.db.query(Site).filter(Site.name == 'goobike').first()
            if not site:
                self.logger.error("GooBike site record not found. Aborting.")
                return
            
            self.site_id = site.id

            # 対象リスティングの取得
            targets = self.db.query(Listing).filter(
                Listing.site_id == self.site_id,
                Listing.shop_id == None,
                Listing.source_url != None
            ).all()

            self.logger.info(f"TARGET COUNT: {len(targets)} listings need shop repair.")

            for listing in targets:
                yield scrapy.Request(
                    url=listing.source_url,
                    callback=self.parse_shop_info,
                    meta={'listing_id': listing.id},
                    dont_filter=True 
                )
        finally:
            self.db.close()

    def parse_shop_info(self, response):
        listing_id = response.meta['listing_id']
        
        try:
            # -------------------------------------------------
            # 1. ショップ識別子 (client_id) の抽出
            # -------------------------------------------------
            shop_identifier = None
            
            # A. input hidden (name="client_id")
            shop_identifier = response.css('input[name="client_id"]::attr(value)').get()
            
            # B. input hidden (name="g_client_id")
            if not shop_identifier:
                shop_identifier = response.css('input[name="g_client_id"]::attr(value)').get()

            # C. JavaScript変数
            if not shop_identifier:
                script_text = response.xpath('//script[contains(text(), "var client_id")]/text()').get()
                if script_text:
                    m = re.search(r"var\s+client_id\s*=\s*['\"](\d+)['\"]", script_text)
                    if m: shop_identifier = m.group(1)

            # D. リンク
            if not shop_identifier:
                shop_href = response.css('a[href*="client_"]::attr(href)').get()
                if shop_href:
                    m = re.search(r'client_(\d+)', shop_href)
                    if m: shop_identifier = m.group(1)

            if not shop_identifier:
                self.logger.warning(f"Could not find shop identifier for listing {listing_id} ({response.url})")
                return

            # -------------------------------------------------
            # 2. ショップ情報の抽出
            # -------------------------------------------------
            
            # 店名
            shop_name = response.css('a.exp::text').get()
            if not shop_name:
                shop_name = response.css('.shop_info_area h3 a::text').get()
            if not shop_name:
                title_text = response.css('title::text').get()
                if title_text:
                    parts = title_text.split('｜')
                    if len(parts) >= 2:
                        shop_name = parts[1].strip()
            if not shop_name:
                candidates = response.css('a[href*="client_"]::text').getall()
                for c in candidates:
                    c = c.strip()
                    if c and "見積" not in c and "問合" not in c and "レビュー" not in c:
                        shop_name = c
                        break
            
            shop_name = shop_name.strip() if shop_name else f"GooBike_Shop_{shop_identifier}"

            # 住所・電話番号 (テーブル構造からXPathで取得)
            address = response.xpath('//td[contains(., "住所")]/following-sibling::td[1]//span/text()').get() or \
                      response.xpath('//td[contains(., "住所")]/following-sibling::td[1]/text()').get() or ""
            address = address.strip().replace('\n', '').replace('\r', '')

            tel = response.xpath('//td[contains(., "TEL")]/following-sibling::td[1]//span/text()').get() or \
                  response.xpath('//td[contains(., "TEL")]/following-sibling::td[1]/text()').get() or ""
            tel = tel.strip().replace('\n', '').replace('\r', '')
            
            # -------------------------------------------------
            # 3. DB更新処理 (住所と電話番号も渡す)
            # -------------------------------------------------
            self.update_database(listing_id, shop_identifier, shop_name, address, tel)

        except Exception as e:
            self.logger.error(f"Error parsing {response.url}: {e}")

    def update_database(self, listing_id, identifier, name, address, tel):
        """
        DB操作を行うメソッド
        """
        db: Session = SessionLocal()
        try:
            # A. 既存のショップ識別子があるか確認
            shop_ident_record = db.query(ShopIdentifier).filter(
                ShopIdentifier.site_id == self.site_id,
                ShopIdentifier.identifier == identifier
            ).first()

            internal_shop_id = None

            if shop_ident_record:
                # 既に存在する店舗ならそのIDを使う
                internal_shop_id = shop_ident_record.shop_id
                self.logger.info(f"Found existing shop: {name} (ID: {internal_shop_id})")
            else:
                # 新規店舗の場合
                self.logger.info(f"Creating NEW shop: {name} (Client: {identifier})")
                
                # 都道府県の簡易抽出
                prefecture = ""
                if address:
                    m = re.match(r'(東京都|北海道|大阪府|京都府|.{2,3}県)', address)
                    if m: prefecture = m.group(1)

                # 都道府県を正式名称に正規化（表記揺れ吸収）
                prefecture = normalize_prefecture(prefecture)

                # DBのNOT NULL制約対策：空の場合はダミー値を入れる
                safe_address = address if address else "-"

                new_shop = Shop(
                    name=name,
                    address=safe_address,
                    phone=tel,
                    prefecture=prefecture,
                )
                db.add(new_shop)
                db.flush() # ID発行

                internal_shop_id = new_shop.id

                # 識別子の紐付け作成
                new_ident = ShopIdentifier(
                    shop_id=new_shop.id,
                    site_id=self.site_id,
                    identifier=identifier
                )
                db.add(new_ident)

            # B. リスティングの更新
            if internal_shop_id:
                listing = db.query(Listing).filter(Listing.id == listing_id).first()
                if listing:
                    listing.shop_id = internal_shop_id
                    db.commit()
                    self.logger.info(f"✅ Listing {listing_id} updated with Shop ID {internal_shop_id}")
                else:
                    self.logger.warning(f"Listing {listing_id} not found during update phase.")
            
        except Exception as e:
            db.rollback()
            self.logger.error(f"DB Error: {e}")
        finally:
            db.close()

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(GooBikeShopFixSpider)
    process.start()