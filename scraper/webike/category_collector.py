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

# 共通設定読み込み (省略可、BDSと同じ構成)
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
sys.path.append(parent_dir)
from utils import normalize_name, extract_displacement
from common.base_spider import MOTOHUB_USER_AGENT

env_path = os.path.join(parent_dir, '..', '.env')
load_dotenv(dotenv_path=env_path)

def get_env_or_exit(key, default=None):
    val = os.getenv(key, default)
    if val is None: sys.exit(1)
    return val

DATABASE_URL = f"mysql+pymysql://{get_env_or_exit('DB_USERNAME')}:{get_env_or_exit('DB_PASSWORD')}@{get_env_or_exit('DB_HOST', 'db')}:{get_env_or_exit('DB_PORT', '3306')}/{get_env_or_exit('DB_DATABASE')}"

engine = create_engine(DATABASE_URL, pool_pre_ping=True)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

class Base(DeclarativeBase): pass

class Category(Base):
    __tablename__ = "categories"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(255), unique=True)
    # Webikeでは画像更新しないのでカラム定義は省略可だが、互換性のため残す
    icon_url = Column(String(255))
    local_icon_path = Column(String(255))

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(255), nullable=False)
    category_id = Column(BigInteger, nullable=True)
    displacement = Column(Integer, nullable=True)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

class CategoryPipeline:
    CATEGORY_MAPPING = {
        "ミニバイク": "ミニバイク",
        "スクーター": "スクーター",
        "ビッグスクーター": "スクーター",
        "ネイキッド": "ネイキッド",
        "スポーツ": "スーパースポーツ",
        "スーパースポーツ": "スーパースポーツ",
        "ツアラー": "ツアラー",
        "アメリカン": "アメリカン",
        "オフロード": "オフロード",
        "電動バイク": "電動バイク",
        "その他": "その他"
    }

    def open_spider(self, spider):
        self.session = SessionLocal()
        self.cat_map = {c.name: c.id for c in self.session.query(Category).all()}
        
        all_models = self.session.query(BikeModel).all()
        self.model_cache = {}
        for m in all_models:
            norm = normalize_name(m.name)
            if norm not in self.model_cache: self.model_cache[norm] = []
            self.model_cache[norm].append(m.id)

    def process_item(self, item, spider):
        # 画像更新用アイテムは無視する
        if item.get('type') == 'category_image':
            return item

        # 車種更新 (既存ロジック)
        webike_cat = item['category_name']
        model_names = item['model_names']
        
        db_cat_name = self.CATEGORY_MAPPING.get(webike_cat, webike_cat)
        cat_id = self.cat_map.get(db_cat_name)
        if not cat_id: return item

        count = 0
        for raw in model_names:
            clean = re.sub(r'\s*[\(\uff08].*', '', raw).strip()
            norm = normalize_name(clean)
            disp = extract_displacement(raw)
            ids = self.model_cache.get(norm, [])
            for tid in ids:
                vals = {"category_id": cat_id}
                if disp:
                    curr = self.session.query(BikeModel).get(tid)
                    if curr and not curr.displacement: vals["displacement"] = disp
                self.session.execute(update(BikeModel).where(BikeModel.id == tid).values(**vals))
                count += 1
        
        if count > 0:
            self.session.commit()
            spider.logger.info(f"Updated {count} models for category '{webike_cat}'")
        
        return item

    def close_spider(self, spider):
        self.session.close()

class WebikeCategorySpider(scrapy.Spider):
    name = "webike_category_collector"
    allowed_domains = ["moto.webike.net"]
    start_urls = ["https://moto.webike.net/"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 4,
        'DOWNLOAD_DELAY': 1.0,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {'__main__.CategoryPipeline': 300},
        'USER_AGENT': MOTOHUB_USER_AGENT,
        'ROBOTSTXT_OBEY': True,
    }

    def parse(self, response):
        """トップページ：カテゴリページへの遷移のみ"""
        # 画像取得ロジックは削除
        items = response.css('ul.category-list li.category-list__item a')
        
        for item in items:
            link = item.css('::attr(href)').get()
            name = item.css('p::text').get()
            
            if link and name:
                yield response.follow(
                    link, 
                    callback=self.parse_category_page,
                    meta={'category_name': name.strip()}
                )

    def parse_category_page(self, response):
        cat_name = response.meta['category_name']
        model_names = response.css('div.model-info p.model_name a::text').getall()
        
        if model_names:
            yield {
                'type': 'model_list',
                'category_name': cat_name,
                'model_names': model_names
            }
        
        next_page = response.css('li.next a::attr(href)').get()
        if next_page:
            yield response.follow(next_page, callback=self.parse_category_page, meta=response.meta)

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(WebikeCategorySpider)
    process.start()