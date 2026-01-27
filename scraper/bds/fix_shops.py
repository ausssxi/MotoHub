import sys
import os

# ==========================================
# 0. インポートパスの解決
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(os.path.dirname(current_dir))
scraper_dir = os.path.dirname(current_dir)

if scraper_dir not in sys.path:
    sys.path.append(scraper_dir)

import scrapy
from scrapy.crawler import CrawlerProcess
import re
import logging
from sqlalchemy.orm import Session

# 共通基盤のインポート
from common.database import SessionLocal, Listing, Shop, ShopIdentifier, Site

class BdsShopFixSpider(scrapy.Spider):
    """
    BDSのリスティングで shop_id が NULL のものを対象に
    詳細ページを巡回し、ショップ情報を特定して紐付けを行うスパイダー。
    """
    name = "bds_shop_fix"
    site_name = "BDS"
    allowed_domains = ["www.bds-bikesensor.net"]

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
            site = self.db.query(Site).filter(Site.name == 'BDS').first()
            if not site:
                self.logger.error("BDS site record not found. Aborting.")
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
            
            # パターンA: リンク href="/shop/client/200063"
            shop_href = response.css('a[href*="/shop/client/"]::attr(href)').get()
            if shop_href:
                m = re.search(r'client/(\d+)', shop_href)
                if m: shop_identifier = m.group(1)

            # パターンB: リンク href="...?shopCode=200063"
            if not shop_identifier:
                shop_href_q = response.css('a[href*="shopCode="]::attr(href)').get()
                if shop_href_q:
                    m = re.search(r'shopCode=(\d+)', shop_href_q)
                    if m: shop_identifier = m.group(1)

            if not shop_identifier:
                self.logger.warning(f"Could not find shop identifier for listing {listing_id} ({response.url})")
                return

            # -------------------------------------------------
            # 2. ショップ情報の抽出
            # -------------------------------------------------
            
            # 店名: ページ上部のリンクテキストなどから取得
            shop_name = response.css('.p-bike_detail_title01 a::text').get() or \
                        response.css('.p-detail_shopinfo_title02 a::text').get() or \
                        response.css('.shop_name a span::text').get()
            
            shop_name = shop_name.strip() if shop_name else f"BDS_Shop_{shop_identifier}"

            # 住所: テーブル構造から取得
            # <tr><td>住所</td><td>...</td></tr> の形
            # XPathを使って「住所」という文字が含まれるセルの、隣のセルのテキストを全て取得して結合
            address_parts = response.xpath('//tr[th[contains(text(),"住所")] or td[contains(text(),"住所")]]/td[last()]//text()').getall()
            address = "".join([p.strip() for p in address_parts if p.strip()])
            
            # 電話番号: telリンクから取得が確実
            tel = response.css('a[href^="tel:"]::attr(href)').re_first(r'tel:([\d-]+)')
            if not tel:
                # テキストから取得を試みる
                tel_parts = response.xpath('//tr[th[contains(text(),"TEL")] or td[contains(text(),"TEL")]]/td[last()]//text()').getall()
                tel_text = "".join(tel_parts)
                m = re.search(r'[\d-]{10,}', tel_text)
                if m: tel = m.group(0)

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
                internal_shop_id = shop_ident_record.shop_id
                self.logger.info(f"Found existing shop: {name} (ID: {internal_shop_id})")
            else:
                self.logger.info(f"Creating NEW shop: {name} (Client: {identifier})")
                
                # 都道府県の簡易抽出
                prefecture = ""
                if address:
                    m = re.match(r'(東京都|北海道|大阪府|京都府|.{2,3}県)', address)
                    if m: prefecture = m.group(1)

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
    process.crawl(BdsShopFixSpider)
    process.start()