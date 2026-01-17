import scrapy
from scrapy.crawler import CrawlerProcess
import os
import re
import sys
import datetime
import logging
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, or_, update
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# ==========================================
# 0. 共通ユーティリティの読み込み
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir) # scraper フォルダ
sys.path.append(parent_dir)

from utils import normalize_name, extract_displacement

# ==========================================
# 1. 環境設定 & DB接続
# ==========================================
env_path = os.path.join(parent_dir, '..', '.env')
load_dotenv(dotenv_path=env_path)

def get_env_or_exit(key, default=None):
    val = os.getenv(key, default)
    if val is None:
        logging.error(f"致命的エラー: 必須の環境変数 '{key}' が設定されていません。")
        sys.exit(1)
    return val

DATABASE_URL = f"mysql+pymysql://{get_env_or_exit('DB_USERNAME')}:{get_env_or_exit('DB_PASSWORD')}@{get_env_or_exit('DB_HOST', 'db')}:{get_env_or_exit('DB_PORT', '3306')}/{get_env_or_exit('DB_DATABASE')}"

engine = create_engine(DATABASE_URL, pool_pre_ping=True)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

class Base(DeclarativeBase):
    pass

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    name = Column(String(255), nullable=False, unique=True)
    displacement = Column(Integer, nullable=True)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

# ==========================================
# 2. Scrapy Pipeline (効率的な更新処理)
# ==========================================
class DisplacementPipeline:
    def open_spider(self, spider):
        self.session = SessionLocal()
        
        spider.logger.info("Building target models cache...")
        # 排気量が空(NULL)または0の車種のみを対象にする
        targets = self.session.query(BikeModel).filter(
            or_(BikeModel.displacement == None, BikeModel.displacement == 0)
        ).all()
        
        # 照合用キャッシュ: {正規化名: {"id": id, "name": name}}
        spider.model_cache = {normalize_name(m.name): {"id": m.id, "name": m.name} for m in targets}
        spider.logger.info(f"Cache complete: {len(spider.model_cache)} models need update.")

    def process_item(self, item, spider):
        model_id = item.get('model_id')
        disp_val = item.get('displacement')
        
        if model_id and disp_val:
            try:
                self.session.execute(
                    update(BikeModel).where(BikeModel.id == model_id).values(displacement=disp_val)
                )
                self.session.commit()
                spider.logger.info(f"  [UPDATED] {item['model_name']} -> {disp_val}cc")
            except Exception as e:
                self.session.rollback()
                spider.logger.error(f"  [FAILED] {item['model_name']}: {e}")
        return item

    def close_spider(self, spider):
        self.session.close()

# ==========================================
# 3. Scrapy Spider (高速巡回ロジック)
# ==========================================
class DisplacementSpider(scrapy.Spider):
    name = "bds_displacement_collector"
    allowed_domains = ["www.bds-bikesensor.net"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 16,
        'DOWNLOAD_DELAY': 0.5,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {'__main__.DisplacementPipeline': 300},
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    }

    # メーカーリストは動的に取得することも可能だが、現状のリストを維持しつつ整理
    maker_list = [
        "honda", "suzuki", "yamaha", "kawasaki", "bmw", "ktm", "aprilia", 
        "mv_agusta", "gilera", "ducati", "triumph", "harley_davidson", 
        "husqvarna", "bimota", "buell", "vespa", "moto_guzzi", "royal_enfield"
    ]

    def start_requests(self):
        if not self.model_cache:
            self.logger.info("No models need displacement update. Skipping.")
            return

        base_url = "https://www.bds-bikesensor.net/bike/maker/"
        for slug in self.maker_list:
            yield scrapy.Request(url=base_url + slug, callback=self.parse_maker_page)

    def parse_maker_page(self, response):
        """一覧ページで排気量取得を試み、不足なら詳細ページへ"""
        # 各車種のカード要素をループ
        model_items = response.css(".model_item")
        
        for item in model_items:
            # 1. 車種名の取得と照合
            raw_title = item.css("a.c-bike_image::attr(title)").get()
            if not raw_title: continue
            
            norm_title = normalize_name(raw_title)
            if norm_title not in self.model_cache: continue
            
            model_data = self.model_cache[norm_title]
            
            # 2. 一覧ページから排気量の抽出を試みる (c-search_name_block_text 内にある場合)
            # 例: 「CB400SF (399cc)」のようなテキストを探す
            disp_val = extract_displacement(raw_title)
            
            if disp_val:
                yield {
                    'model_id': model_data['id'],
                    'model_name': model_data['name'],
                    'displacement': disp_val
                }
                continue # 一覧で取れたので詳細ページへは行かない

            # 3. 一覧で取れなかった場合のみ詳細ページへ遷移
            detail_href = item.css("a.c-bike_image::attr(href)").get()
            if detail_href:
                yield response.follow(
                    url=detail_href,
                    callback=self.parse_detail_page,
                    meta={'model_id': model_data['id'], 'model_name': model_data['name']}
                )

    def parse_detail_page(self, response):
        """詳細ページからの最終手段の抽出"""
        model_id = response.meta['model_id']
        model_name = response.meta['model_name']
        
        # スペック表の「排気量」項目を探す
        status_cols = response.css(".c-search_status_col")
        disp_val = None
        
        for col in status_cols:
            head = col.css(".c-search_status_head::text").get()
            if head and "排気量" in head:
                val_text = col.css(".c-search_status_title01::text").get()
                if val_text:
                    match = re.search(r'(\d+)', val_text)
                    if match:
                        disp_val = int(match.group(1))
                        break
        
        if disp_val:
            yield {
                'model_id': model_id,
                'model_name': model_name,
                'displacement': disp_val
            }

if __name__ == "__main__":
    print(">>> BDS Displacement Collector Started.")
    process = CrawlerProcess()
    process.crawl(DisplacementSpider)
    process.start()