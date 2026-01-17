import scrapy
from scrapy.crawler import CrawlerProcess
from scrapy.signalmanager import dispatcher
from scrapy import signals
import os
import re
import datetime
import sys
import logging
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, BigInteger, String, Numeric, Integer, Boolean, Text, JSON, DateTime, update, or_
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# ==========================================
# 0. 共通ユーティリティの読み込み
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir) # scraper フォルダ
sys.path.append(parent_dir)

from utils import normalize_name

# ==========================================
# 1. 環境設定 & データベース定義
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

class Base(DeclarativeBase): pass

class Listing(Base):
    __tablename__ = "listings"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    bike_model_id = Column(BigInteger, nullable=True)
    shop_id = Column(BigInteger, nullable=True)
    site_id = Column(BigInteger, nullable=False)
    title = Column(String(255), nullable=True)
    source_url = Column(Text, nullable=False)
    price = Column(Numeric(12, 0))
    total_price = Column(Numeric(12, 0), nullable=True)
    model_year = Column(Integer, nullable=True)
    mileage = Column(Integer, nullable=True)
    first_registration = Column(String(50), nullable=True)
    image_urls = Column(JSON, nullable=True)
    has_repair_history = Column(Boolean, default=False)
    condition = Column(String(50), nullable=True)
    color = Column(String(50), nullable=True)
    is_sold_out = Column(Boolean, default=False)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

class BikeModelIdentifier(Base):
    __tablename__ = "bike_model_identifiers"
    id = Column(BigInteger, primary_key=True)
    bike_model_id = Column(BigInteger, nullable=False)
    site_id = Column(BigInteger, nullable=False)
    identifier = Column(String(100), nullable=False)

class ShopIdentifier(Base):
    __tablename__ = "shop_identifiers"
    id = Column(BigInteger, primary_key=True)
    shop_id = Column(BigInteger, nullable=False)
    site_id = Column(BigInteger, nullable=False)
    identifier = Column(String(100), nullable=False)

class Site(Base):
    __tablename__ = "sites"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(50))

# ==========================================
# 2. Database Pipeline (新規保存と更新を分離)
# ==========================================
class ListingPipeline:
    def open_spider(self, spider):
        self.session = SessionLocal()

    def process_item(self, item, spider):
        try:
            if item.get('is_update'):
                # 既存商品の価格更新
                self.session.execute(
                    update(Listing).where(Listing.source_url == item['source_url'])
                    .values(
                        price=item['price'],
                        total_price=item['total_price'],
                        is_sold_out=False, # 再掲載対応
                        updated_at=datetime.datetime.now()
                    )
                )
            else:
                # 新規保存 (一括ではなく1件ずつのほうがクロスサイトチェックが容易)
                new_listing = Listing(
                    bike_model_id=item['bike_model_id'],
                    shop_id=item['shop_id'],
                    site_id=spider.site_id,
                    title=item['title'],
                    source_url=item['source_url'],
                    price=item['price'],
                    total_price=item['total_price'],
                    model_year=item['model_year'],
                    mileage=item['mileage'],
                    first_registration=item.get('first_registration'),
                    has_repair_history=item.get('has_repair_history', False),
                    condition='中古車',
                    color=item.get('color'),
                    image_urls=item['image_urls'],
                    is_sold_out=False
                )
                self.session.add(new_listing)
            
            self.session.commit()
        except Exception as e:
            self.session.rollback()
            spider.logger.error(f"DB保存エラー: {e}")
        return item

    def close_spider(self, spider):
        self.session.close()

# ==========================================
# 3. Scrapy Spider
# ==========================================
class BDSListingSpider(scrapy.Spider):
    name = "bds_listings"
    allowed_domains = ["www.bds-bikesensor.net"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 16,
        'DOWNLOAD_DELAY': 0.5,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {'__main__.ListingPipeline': 300},
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    }

    MAKER_LIST = ["honda", "suzuki", "yamaha", "kawasaki", "bmw", "ktm", "ducati", "harley_davidson", "triumph"]

    def __init__(self, *args, **kwargs):
        super(BDSListingSpider, self).__init__(*args, **kwargs)
        db = SessionLocal()
        site = db.query(Site).filter(Site.name == "BDS").first()
        self.site_id = site.id if site else 0

        self.model_ident_cache = {i.identifier: i.bike_model_id for i in db.query(BikeModelIdentifier).filter(BikeModelIdentifier.site_id == self.site_id).all()}
        self.shop_cache = {i.identifier: i.shop_id for i in db.query(ShopIdentifier).filter(ShopIdentifier.site_id == self.site_id).all()}
        # 現在「出品中」の全サイトの車両をキャッシュ（クロスサイト重複チェック用）
        self.active_listings = db.query(Listing).filter(Listing.is_sold_out == False).all()
        
        self.known_urls = {l.source_url for l in self.active_listings if l.site_id == self.site_id}
        self.found_urls = set()
        db.close()
        dispatcher.connect(self.spider_closed, signals.spider_closed)

    def start_requests(self):
        base_url = "https://www.bds-bikesensor.net/bike/maker/"
        for slug in self.MAKER_LIST:
            yield scrapy.Request(url=base_url + slug, callback=self.parse_maker_page)

    def parse_maker_page(self, response):
        model_items = response.css("div.col, .model_item")
        for item in model_items:
            m_input = item.css("input.model-checkbox::attr(value)").get()
            href = item.css("a.c-bike_image::attr(href), a.c-link_block::attr(href)").get()
            if m_input and href:
                bike_model_id = self.model_ident_cache.get(m_input)
                if bike_model_id:
                    yield response.follow(href, callback=self.parse_listings, meta={'bike_model_id': bike_model_id})

    def parse_listings(self, response):
        bike_model_id = response.meta['bike_model_id']
        bike_blocks = response.css("li.type_bike, li.type_bike_sp")
        
        for bike in bike_blocks:
            v_url = response.urljoin(bike.css(".c-search_block_title a::attr(href), .c-search_block_title02 a::attr(href)").get())
            if not v_url: continue
            self.found_urls.add(v_url)

            # データ抽出
            item_data = self.extract_bike_data(response, bike, bike_model_id, v_url)
            if not item_data: continue

            # 1. URLが既知なら「更新」
            if v_url in self.known_urls:
                item_data['is_update'] = True
                yield item_data
            else:
                # 2. 新規の場合、クロスサイト重複チェック
                # 同じ店、同じ車種、同じ年式、同じ距離なら別サイトの同一車両
                if item_data['shop_id']:
                    is_dup = any(
                        l.shop_id == item_data['shop_id'] and 
                        l.bike_model_id == item_data['bike_model_id'] and
                        l.model_year == item_data['model_year'] and
                        l.mileage == item_data['mileage']
                        for l in self.active_listings
                    )
                    if is_dup:
                        self.logger.info(f"  [DUP SKIP] Cross-site duplicate: {item_data['title']}")
                        continue
                
                item_data['is_new'] = True
                yield item_data

        # ページネーション
        next_page = response.css("div.c-pager a.c-btn_next::attr(href)").get()
        if next_page:
            yield response.follow(next_page, callback=self.parse_listings, meta=response.meta)

    def extract_bike_data(self, response, bike, bike_model_id, v_url):
        # 価格、スペック、店舗、画像の抽出（ロジックは概ね維持しつつ整理）
        price_val, total_price_val = 0, None
        for p_item in bike.css(".c-search_block_price"):
            l_text = p_item.css(".c-search_block_price_title::text").get()
            v_text = "".join(p_item.css(".c-search_block_price_text *::text").getall()).replace(',', '').strip()
            match = re.search(r'(\d+\.?\d*)', v_text)
            if match:
                num = int(float(match.group(1)) * 10000)
                if l_text and "本体価格" in l_text: price_val = num
                elif l_text and "支払総額" in l_text: total_price_val = num

        year, mile, first_reg, color = None, None, None, None
        has_repair = False
        for col in bike.css(".c-search_status_col"):
            h_txt = col.css(".c-search_status_head::text").get() or ""
            v_txt = "".join(col.css(".c-search_status_title01 *::text").getall()).strip()
            if "モデル年" in h_txt:
                y_m = re.search(r'(\d{4})', v_txt)
                if y_m: year = int(y_m.group(1))
            elif "距離" in h_txt:
                m_m = re.search(r'(\d+)', v_txt.replace(',', ''))
                if m_m: mile = int(m_m.group(1))
            elif "初度登録" in h_txt: first_reg = v_txt
            elif "修復歴" in h_txt: has_repair = "あり" in v_txt
            elif "色" in h_txt: color = v_txt

        shop_href = bike.css(".c-search_block_bottom_lead a::attr(href), .c-search_block_shop_title01 a::attr(href)").get()
        shop_id = None
        if shop_href:
            id_match = re.search(r'client/(\d+)', shop_href)
            if id_match: shop_id = self.shop_cache.get(id_match.group(1))

        if not shop_id: return None # ショップが見つからない車両は登録しない

        img_url = bike.css("figure.c-img_cover::attr(data-src)").get() or bike.css("figure.c-img_cover::attr(src)").get()

        return {
            'bike_model_id': bike_model_id,
            'shop_id': shop_id,
            'title': (bike.css(".c-search_block_title a::text, .c-search_block_title02 a::text").get() or "").strip(),
            'source_url': v_url,
            'price': price_val,
            'total_price': total_price_val,
            'model_year': year,
            'mileage': mile,
            'first_registration': first_reg,
            'has_repair_history': has_repair,
            'color': color,
            'image_urls': [response.urljoin(img_url)] if img_url else []
        }

    def spider_closed(self, spider):
        session = SessionLocal()
        missing_urls = self.known_urls - self.found_urls
        if missing_urls:
            missing_list = list(missing_urls)
            for i in range(0, len(missing_list), 100):
                chunk = missing_list[i:i + 100]
                session.execute(update(Listing).where(Listing.source_url.in_(chunk)).where(Listing.site_id == self.site_id).values(is_sold_out=True, updated_at=datetime.datetime.now()))
            session.commit()
        session.close()

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(BDSListingSpider)
    process.start()