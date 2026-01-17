import scrapy
from scrapy.crawler import CrawlerProcess
import os
import re
import sys
import datetime
import logging
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, update
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# ==========================================
# 0. パス調整 & 共通ユーティリティ読込
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir) # scraper フォルダ
sys.path.append(parent_dir)

# 共通関数をインポート
from utils import normalize_name, extract_displacement

# ==========================================
# 1. データベース設定 & モデル定義
# ==========================================
env_path = os.path.join(parent_dir, '..', '.env')
load_dotenv(dotenv_path=env_path)

def get_env_or_exit(key, default=None):
    val = os.getenv(key, default)
    if val is None:
        logging.error(f"致命的エラー: 必須の環境変数 '{key}' が設定されていません。")
        sys.exit(1)
    return val

DB_USER = get_env_or_exit("DB_USERNAME")
DB_PASS = get_env_or_exit("DB_PASSWORD")
DB_NAME = get_env_or_exit("DB_DATABASE")
DB_HOST = get_env_or_exit("DB_HOST", default="db")
DB_PORT = get_env_or_exit("DB_PORT", default="3306")

DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}"

engine = create_engine(DATABASE_URL, pool_pre_ping=True)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

class Base(DeclarativeBase):
    pass

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(255), nullable=False)
    category = Column(String(50), nullable=True)
    displacement = Column(Integer, nullable=True)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

# ==========================================
# 2. Scrapy Pipeline (DB更新処理)
# ==========================================
class CategoryPipeline:
    def open_spider(self, spider):
        spider.logger.info("名寄せ用キャッシュを構築中...")
        self.session = SessionLocal()
        
        # 全車種をメモリ上に展開し、名寄せを高速化する
        # DB上の名前も正規化してマッチング精度を上げる
        all_models = self.session.query(BikeModel).all()
        self.model_cache = {}
        for m in all_models:
            norm_key = normalize_name(m.name)
            if norm_key not in self.model_cache:
                self.model_cache[norm_key] = []
            self.model_cache[norm_key].append(m)
        
        spider.logger.info(f"キャッシュ構築完了: {len(self.model_cache)}件の名寄せパターンを保持しています。")

    def process_item(self, item, spider):
        category_name = item['category_name']
        scraped_model_names = item['model_names']
        update_count = 0

        for full_text in scraped_model_names:
            # 1. 車種名の正規化 (BDS特有の「(125台)」などを除去)
            clean_name = re.sub(r'\s*[\(\uff08].*', '', full_text).strip()
            norm_name = normalize_name(clean_name)
            
            # 2. 排気量の推測
            inferred_disp = extract_displacement(full_text)
            
            # 3. キャッシュから該当する車種IDを取得
            target_models = self.model_cache.get(norm_name, [])
            
            for model_obj in target_models:
                try:
                    needs_update = False
                    
                    # カテゴリーが未設定、または「不明」の場合に更新
                    if not model_obj.category or model_obj.category == "不明":
                        model_obj.category = category_name
                        needs_update = True
                    
                    # 排気量が未設定の場合のみ補完
                    if inferred_disp and not model_obj.displacement:
                        model_obj.displacement = inferred_disp
                        needs_update = True
                    
                    if needs_update:
                        # ループ内クエリを避け、オブジェクトの属性更新を利用（最後に一括コミット）
                        update_count += 1
                except Exception as e:
                    spider.logger.error(f"Error checking model {model_obj.id}: {e}")

        if update_count > 0:
            self.session.commit()
            spider.logger.info(f"カテゴリー '{category_name}': {update_count}件を更新しました。")
        
        return item

    def close_spider(self, spider):
        self.session.close()

# ==========================================
# 3. Scrapy Spider
# ==========================================
class CategorySpider(scrapy.Spider):
    name = "bds_category_collector"
    allowed_domains = ["www.bds-bikesensor.net"]
    
    # BDSのカテゴリー定義
    CATEGORIES = [
        {"slug": "gentsuki", "name": "原付スクーター"},
        {"slug": "scooter51_125", "name": "スクーター/51～125cc"},
        {"slug": "big_scooter", "name": "スクーター/126cc以上"},
        {"slug": "naked", "name": "ネイキッド"},
        {"slug": "sports", "name": "スポーツ/レプリカ"},
        {"slug": "classic", "name": "クラシック"},
        {"slug": "offroad", "name": "オフロード"},
        {"slug": "american", "name": "アメリカン"},
        {"slug": "tourer", "name": "ツアラー"},
        {"slug": "adventure", "name": "アドベンチャー"},
        {"slug": "streetfighter", "name": "ストリートファイター"},
        {"slug": "minibike", "name": "ミニバイク"},
        {"slug": "ev", "name": "EV"},
        {"slug": "other", "name": "その他"}
    ]

    custom_settings = {
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 0.8,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {'__main__.CategoryPipeline': 300},
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    }

    def start_requests(self):
        base_url = "https://www.bds-bikesensor.net/bike/type/"
        for cat in self.CATEGORIES:
            yield scrapy.Request(
                url=base_url + cat['slug'],
                callback=self.parse,
                meta={'category_name': cat['name']}
            )

    def parse(self, response):
        category_name = response.meta['category_name']
        # BDSの車種名ブロックからテキストを一括取得
        model_names = response.css(".c-search_name_block_text::text").getall()
        
        if model_names:
            yield {
                'category_name': category_name,
                'model_names': model_names
            }

if __name__ == "__main__":
    print(">>> BDS Category Collector Started.")
    logging.basicConfig(level=logging.INFO)
    
    process = CrawlerProcess()
    process.crawl(CategorySpider)
    process.start()