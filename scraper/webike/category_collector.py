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
        
        # 全車種をメモリ上に展開
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
        model_names = item['model_names']
        update_count = 0

        for full_text in model_names:
            # 1. 車種名の正規化
            clean_name = re.sub(r'\s*[\(\uff08].*', '', full_text).strip()
            norm_name = normalize_name(clean_name)
            
            # 2. 排気量の推測
            inferred_disp = extract_displacement(full_text)
            
            # 3. キャッシュから該当する車種オブジェクトを取得
            target_models = self.model_cache.get(norm_name, [])
            
            for model_obj in target_models:
                try:
                    needs_update = False
                    
                    # カテゴリーが未設定の場合に更新
                    if not model_obj.category or model_obj.category == "不明":
                        model_obj.category = category_name
                        needs_update = True
                    
                    # 排気量が未設定の場合のみ補完
                    if inferred_disp and not model_obj.displacement:
                        model_obj.displacement = inferred_disp
                        needs_update = True
                    
                    if needs_update:
                        update_count += 1
                except Exception as e:
                    spider.logger.error(f"ID {model_obj.id} の更新判定エラー: {e}")

        if update_count > 0:
            self.session.commit()
            spider.logger.info(f"カテゴリー '{category_name}': {update_count}件を更新しました。")
        
        return item

    def close_spider(self, spider):
        self.session.close()

# ==========================================
# 3. Scrapy Spider (Webike カテゴリー巡回)
# ==========================================
class WebikeCategorySpider(scrapy.Spider):
    name = "webike_category_collector"
    allowed_domains = ["moto.webike.net"]
    start_urls = ["https://moto.webike.net/"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 4,
        'DOWNLOAD_DELAY': 1.0,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {'__main__.CategoryPipeline': 300},
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    }

    def parse(self, response):
        """トップページからカテゴリー一覧を取得"""
        # ul.category-list 内の各カテゴリー要素をループ
        categories = response.css('ul.category-list li.category-list__item a')
        
        for cat in categories:
            href = cat.css('::attr(href)').get()
            name = cat.css('p::text').get()
            
            if href and name:
                yield response.follow(
                    href,
                    callback=self.parse_category_page,
                    meta={'category_name': name.strip()}
                )

    def parse_category_page(self, response):
        """各カテゴリー内の車種リストを取得"""
        category_name = response.meta['category_name']
        
        # ご提示いただいたカテゴリーページのソースに基づいたセレクタ
        # model-info クラス内の p.model_name リンクテキストを取得
        model_names = response.css('div.model-info p.model_name a::text').getall()
        
        if model_names:
            yield {
                'category_name': category_name,
                'model_names': model_names
            }
        
        # ページネーション（次へ）がある場合は辿る
        # Webikeのリスト表示形式に合わせた「次へ」ボタンの取得
        next_page = response.css('li.next a::attr(href)').get()
        if next_page:
            yield response.follow(
                next_page,
                callback=self.parse_category_page,
                meta=response.meta
            )

if __name__ == "__main__":
    print(">>> Webike Category Collector Started.")
    logging.basicConfig(level=logging.INFO)
    
    process = CrawlerProcess()
    process.crawl(WebikeCategorySpider)
    process.start()