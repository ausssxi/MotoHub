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

class GooBikeShopFixSpider(scrapy.Spider):
    """
    GooBikeのリスティングで shop_id が NULL のものを対象に
    詳細ページを巡回し、ショップ情報を補完するスパイダー
    """
    name = "goobike_shop_fix"
    site_name = "GooBike"
    allowed_domains = ["www.goobike.com"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 8, # 修復用なので負荷をかけすぎないように少し抑えめ
        'DOWNLOAD_DELAY': 0.5,
        'RANDOMIZE_DOWNLOAD_DELAY': True,
        'COOKIES_ENABLED': True,
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'ITEM_PIPELINES': {}, 
    }

    def start_requests(self):
        self.db = SessionLocal()
        try:
            # 1. GooBikeのサイトIDを取得
            # common/database.py の定義に基づき Site.name で検索
            site = self.db.query(Site).filter(Site.name == 'GooBike').first()
            
            if not site:
                self.logger.error("GooBike site record not found (searched by name='GooBike').")
                # 名前が小文字登録されている可能性も考慮して再トライ
                site = self.db.query(Site).filter(Site.name == 'goobike').first()

            if not site:
                self.logger.error("GooBike site record not found. Aborting.")
                return
            
            self.site_id = site.id

            # 2. shop_id が NULL の GooBike リスティングを取得
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
            shop_href = response.css('a[href*="client_"]::attr(href)').get()
            
            shop_identifier = None
            if shop_href:
                m = re.search(r'client_(\d+)', shop_href)
                if m:
                    shop_identifier = m.group(1)

            if not shop_identifier:
                self.logger.warning(f"Could not find shop identifier for listing {listing_id} ({response.url})")
                return

            # -------------------------------------------------
            # 2. ショップ詳細情報の抽出
            # -------------------------------------------------
            shop_name = response.css('.shop_info_area h3 a::text').get() or \
                        response.css('.shop_info_area h3::text').get() or \
                        response.css('.detail_shop_name a::text').get() or \
                        "不明な販売店"
            
            shop_name = shop_name.strip()

            # 住所・TEL
            address = response.css('.shop_address::text').get() or ""
            tel = response.css('.shop_tel::text').get() or ""
            
            # -------------------------------------------------
            # 3. DB更新処理
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
            # A. 既存のショップがあるか確認
            shop_ident_record = db.query(ShopIdentifier).filter(
                ShopIdentifier.site_id == self.site_id,
                ShopIdentifier.identifier == identifier
            ).first()

            internal_shop_id = None

            if shop_ident_record:
                # 既に存在する店舗
                internal_shop_id = shop_ident_record.shop_id
                self.logger.info(f"Found existing shop: {name} (ID: {internal_shop_id})")
            else:
                # 新規店舗の作成
                self.logger.info(f"Creating NEW shop: {name} (Client: {identifier})")
                
                # 都道府県の簡易抽出 (住所の先頭3~4文字)
                prefecture = ""
                if address:
                    m = re.match(r'(東京都|北海道|大阪府|京都府|.{2,3}県)', address)
                    if m: prefecture = m.group(1)

                new_shop = Shop(
                    name=name,
                    address=address,
                    phone=tel, # 修正: Shopモデルの定義は 'phone' なので 'tel' ではなく 'phone' に代入
                    prefecture=prefecture,
                )
                db.add(new_shop)
                db.flush() # ID発行

                internal_shop_id = new_shop.id

                # 識別子の紐付け
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