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
import logging
from sqlalchemy.orm import Session

# 共通基盤のインポート
from common.database import SessionLocal, Listing, Site
from common.base_spider import MOTOHUB_USER_AGENT

class WebikeDescriptionFixSpider(scrapy.Spider):
    """
    Webikeのリスティングで description が NULL のものを対象に
    詳細ページを巡回し、説明文を補完するスパイダー
    """
    name = "webike_description_fix"
    site_name = "Webike"
    allowed_domains = ["moto.webike.net"]

    custom_settings = {
        # 高速化設定（Webikeはページ遷移が多いので少し並列数を上げます）
        'CONCURRENT_REQUESTS': 16,
        'DOWNLOAD_DELAY': 0.5,
        'RANDOMIZE_DOWNLOAD_DELAY': True,
        'AUTOTHROTTLE_ENABLED': True,
        'AUTOTHROTTLE_START_DELAY': 0.5,
        'AUTOTHROTTLE_MAX_DELAY': 10,
        'AUTOTHROTTLE_TARGET_CONCURRENCY': 1.0,
        'COOKIES_ENABLED': True,
        'USER_AGENT': MOTOHUB_USER_AGENT,
        'ROBOTSTXT_OBEY': True,
        'ITEM_PIPELINES': {}, 
    }

    def start_requests(self):
        self.db = SessionLocal()
        try:
            # 1. WebikeのサイトIDを取得
            site = self.db.query(Site).filter(Site.name == 'Webike').first()
            if not site:
                self.logger.error("Webike site record not found.")
                return
            
            self.site_id = site.id

            # 2. description が NULL の Webike リスティングを取得
            targets = self.db.query(Listing).filter(
                Listing.site_id == self.site_id,
                Listing.description == None,
                Listing.source_url != None
            ).all()

            self.logger.info(f"TARGET COUNT: {len(targets)} listings need description.")

            for listing in targets:
                yield scrapy.Request(
                    url=listing.source_url,
                    callback=self.parse_description,
                    meta={'listing_id': listing.id},
                    dont_filter=True 
                )
        finally:
            self.db.close()

    def parse_description(self, response):
        listing_id = response.meta['listing_id']
        
        try:
            # -------------------------------------------------
            # 説明文の抽出ロジック
            # -------------------------------------------------
            
            # 1. キャッチコピー (<p class="text-catchcopy">)
            catchcopy = response.css('.text-catchcopy::text').get() or ""
            
            # 2. 本文 (<div class="description">)
            # div内のすべてのテキストを取得し、改行で結合します
            desc_lines = response.css('.description *::text').getall()
            body = "\n".join([line.strip() for line in desc_lines if line.strip()])
            
            # 3. 結合 (キャッチコピー + 改行 + 本文)
            full_text_parts = []
            if catchcopy.strip():
                full_text_parts.append(catchcopy.strip())
            if body:
                full_text_parts.append(body)
            
            description = "\n\n".join(full_text_parts)

            if description:
                self.update_database(listing_id, description)
            else:
                self.logger.debug(f"Description not found for {listing_id}: {response.url}")

        except Exception as e:
            self.logger.error(f"Error parsing {response.url}: {e}")

    def update_database(self, listing_id, description):
        """DB更新処理"""
        db: Session = SessionLocal()
        try:
            listing = db.query(Listing).filter(Listing.id == listing_id).first()
            if listing:
                listing.description = description
                db.commit()
                self.logger.info(f"✅ Updated listing {listing_id}")
            
        except Exception as e:
            db.rollback()
            self.logger.error(f"DB Error: {e}")
        finally:
            db.close()

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(WebikeDescriptionFixSpider)
    process.start()