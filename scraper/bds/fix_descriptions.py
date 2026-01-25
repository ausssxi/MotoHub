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

class BdsDescriptionFixSpider(scrapy.Spider):
    """
    BDSのリスティングで description が NULL のものを対象に
    詳細ページを巡回し、説明文を補完するスパイダー
    """
    name = "bds_description_fix"
    site_name = "BDS"
    allowed_domains = ["www.bds-bikesensor.net"]

    custom_settings = {
        # 【高速化・最適化設定】
        'CONCURRENT_REQUESTS': 16,       # 並列数を8から16に倍増
        'DOWNLOAD_DELAY': 0.5,           # 待機時間を1.0から0.5に短縮
        'RANDOMIZE_DOWNLOAD_DELAY': True,
        
        # 【重要】オートスロットル（自動調整機能）を有効化
        # 相手サーバーの負荷状況を検知して、自動的にアクセス頻度を調整します。
        # これにより、高速化しつつBANされるリスクを最小限に抑えます。
        'AUTOTHROTTLE_ENABLED': True,
        'AUTOTHROTTLE_START_DELAY': 0.5, # 初期の遅延時間
        'AUTOTHROTTLE_MAX_DELAY': 10,    # 最大遅延時間
        'AUTOTHROTTLE_TARGET_CONCURRENCY': 1.0, # サーバーへの同時接続数の目安

        'COOKIES_ENABLED': True,
        # User-Agentは必須
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'ITEM_PIPELINES': {}, 
    }

    def start_requests(self):
        self.db = SessionLocal()
        try:
            # 1. BDSのサイトIDを取得
            site = self.db.query(Site).filter(Site.name == 'BDS').first()
            if not site:
                self.logger.error("BDS site record not found.")
                return
            
            self.site_id = site.id

            # 2. description が NULL の BDS リスティングを取得
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
            description = ""

            # パターン1: PC版レイアウト
            lines = response.css('.p-detail_shopinfo_pc p::text').getall()
            description = "\n".join([line.strip() for line in lines if line.strip()])

            # パターン2: スマホ版レイアウト
            if not description:
                lines = response.css('.p-detail_pr_text::text').getall()
                description = "\n".join([line.strip() for line in lines if line.strip()])

            if description:
                self.update_database(listing_id, description.strip())
            else:
                # 見つからない場合もログレベルを下げて進行を妨げないようにする
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
    process.crawl(BdsDescriptionFixSpider)
    process.start()