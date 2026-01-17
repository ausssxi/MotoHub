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
    local_image_paths = Column(JSON, nullable=True)
    has_repair_history = Column(Boolean, default=False)
    condition = Column(String(50), nullable=True)
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
# 2. Database Pipeline (高速化と更新の両立)
# ==========================================
class ListingPipeline:
    def open_spider(self, spider):
        self.session = SessionLocal()

    def process_item(self, item, spider):
        try:
            if item.get('is_update'):
                # 既存情報の価格・売切状態更新
                self.session.execute(
                    update(Listing).where(Listing.source_url == item['source_url'])
                    .values(
                        price=item['price'],
                        total_price=item['total_price'],
                        is_sold_out=False,
                        updated_at=datetime.datetime.now()
                    )
                )
            else:
                # 新規登録
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
                    has_repair_history=item.get('has_repair_history', False),
                    condition=item.get('condition', '中古車'),
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
# 3. Webike Listing Spider
# ==========================================
class WebikeListingSpider(scrapy.Spider):
    name = "webike_listings"
    allowed_domains = ["moto.webike.net"]
    start_urls = ["https://moto.webike.net/maker/"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 16,
        'DOWNLOAD_DELAY': 0.5,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {'__main__.ListingPipeline': 300},
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    }

    def __init__(self, *args, **kwargs):
        super(WebikeListingSpider, self).__init__(*args, **kwargs)
        db = SessionLocal()
        site = db.query(Site).filter(Site.name == "Webike").first()
        self.site_id = site.id if site else 0

        # キャッシュ構築
        self.model_ident_cache = {i.identifier: i.bike_model_id for i in db.query(BikeModelIdentifier).filter(BikeModelIdentifier.site_id == self.site_id).all()}
        self.shop_cache = {i.identifier: i.shop_id for i in db.query(ShopIdentifier).filter(ShopIdentifier.site_id == self.site_id).all()}
        self.active_listings = db.query(Listing).filter(Listing.is_sold_out == False).all()
        self.known_urls = {l.source_url for l in self.active_listings if l.site_id == self.site_id}
        self.found_urls = set()
        db.close()
        
        if not self.model_ident_cache:
            logging.error("モデル識別子がキャッシュされていません。先に model_collector を実行してください。")

        dispatcher.connect(self.spider_closed, signals.spider_closed)

    def parse(self, response):
        """1. メーカー一覧ページから全メーカーURLを取得"""
        # 国内・海外すべてのリンクを取得
        maker_links = response.css('div.maker ul.dotline li a::attr(href)').getall()
        for href in set(maker_links):
            yield response.follow(href, callback=self.parse_models)

    def parse_models(self, response):
        """2. メーカー内車種一覧ページから車種URL(list/)を取得"""
        # 修正：複数のセレクタで車種を確実に捉える
        # また、hrefから直接識別子を抜くロジックを強化
        bike_items = response.css('div.motoset ul.dotline li, div#category_search_list div.moto ul li')
        
        found_in_page = 0
        for item in bike_items:
            href = item.css('p.model_name a::attr(href)').get() or item.css('a.img-thumbnail::attr(href)').get()
            if not href: continue

            # 識別子の特定: 1. input値(あれば優先) 2. URLのスラッシュ間
            identifier = item.css('input[name="model_code_checkList"]::attr(value)').get()
            if not identifier:
                # URL例: https://moto.webike.net/HONDA/CB400SF/list/ -> CB400SF
                parts = [p for p in href.split('/') if p]
                if len(parts) >= 2:
                    # 最後が 'list' ならその前、そうでなければ最後を取る
                    identifier = parts[-2] if parts[-1] == 'list' else parts[-1]

            if not identifier: continue

            bike_model_id = self.model_ident_cache.get(identifier)
            if bike_model_id:
                # 確実に /list/ ページへ飛ばす
                list_url = href if href.endswith('list/') else href.rstrip('/') + '/list/'
                found_in_page += 1
                yield response.follow(list_url, callback=self.parse_listings, meta={'bike_model_id': bike_model_id})

        if found_in_page == 0:
            self.logger.warning(f"No valid models found at {response.url}. Check selectors or identifiers.")

    def parse_listings(self, response):
        """3. 出品車両一覧ページを解析"""
        bike_model_id = response.meta['bike_model_id']
        listings = response.css('li.li_bike_list:not(.recommend-block)')
        
        self.logger.info(f"Listing Page: {response.url} - Found {len(listings)} vehicles.")

        for li in listings:
            v_link = li.css('a.flex::attr(href)').get()
            if not v_link: continue
            
            v_url = response.urljoin(v_link)
            self.found_urls.add(v_url)

            # データ抽出
            item_data = self.extract_listing_data(response, li, bike_model_id, v_url)
            if not item_data: continue

            if v_url in self.known_urls:
                item_data['is_update'] = True
                yield item_data
            else:
                # クロスサイト重複チェック
                if item_data['shop_id']:
                    is_dup = any(
                        l.shop_id == item_data['shop_id'] and 
                        l.bike_model_id == item_data['bike_model_id'] and
                        l.model_year == item_data['model_year'] and
                        l.mileage == item_data['mileage']
                        for l in self.active_listings
                    )
                    if is_dup:
                        continue
                
                item_data['is_new'] = True
                yield item_data

        # ページネーション (ul.pagination li.current の次)
        next_page = response.css('ul.pagination li.current + li a::attr(href)').get()
        if next_page:
            yield response.follow(next_page, callback=self.parse_listings, meta=response.meta)

    def extract_listing_data(self, response, li, bike_model_id, v_url):
        try:
            # タイトル
            title = li.css('h2 strong::text').get(default="").strip()

            # 価格 (ASK対応)
            price_text = li.css('.prices li.small-price span::text').get() or "0"
            price_val = 0
            if "ASK" not in price_text:
                p_match = re.search(r'(\d+\.?\d*)', price_text.replace(',', ''))
                if p_match: price_val = int(float(p_match.group(1)) * 10000)

            total_text = li.css('.prices li:not(.small-price) span::text').get() or ""
            total_val = None
            if total_text and "ASK" not in total_text and "―" not in total_text:
                t_match = re.search(r'(\d+\.?\d*)', total_text.replace(',', ''))
                if t_match: total_val = int(float(t_match.group(1)) * 10000)

            # スペック
            mile_text = li.css('.box-distace li.border .distance span::text').get() or ""
            mile = 0
            if all(s not in mile_text for s in ["走行不明", "減算歴車", "-"]):
                m_match = re.search(r'(\d+)', mile_text.replace(',', ''))
                if m_match: mile = int(m_match.group(1))

            year_text = li.css('.box-distace li:not(.border) .distance span::text').get() or ""
            year = None
            y_match = re.search(r'(\d{4})', year_text)
            if y_match: year = int(y_match.group(1))

            # 店舗ID
            shop_id = None
            shop_href = li.css('.shop_name a::attr(href)').get()
            if shop_href:
                s_match = re.search(r'shop/(\d+)', shop_href)
                if s_match:
                    shop_id = self.shop_cache.get(s_match.group(1))

            if not shop_id: return None

            # 画像
            main_img = li.css('.img_bike_list img::attr(data-src)').get()
            sub_imgs = li.css('.img_bike_list ul li ul li img::attr(data-src)').getall()
            image_urls = []
            if main_img: image_urls.append(response.urljoin(main_img))
            for img in sub_imgs:
                image_urls.append(response.urljoin(img))

            return {
                'bike_model_id': bike_model_id,
                'shop_id': shop_id,
                'title': title,
                'source_url': v_url,
                'price': price_val,
                'total_price': total_val,
                'model_year': year,
                'mileage': mile,
                'image_urls': image_urls
            }
        except Exception:
            return None

    def spider_closed(self, spider):
        session = SessionLocal()
        missing_urls = self.known_urls - self.found_urls
        if missing_urls:
            missing_list = list(missing_urls)
            for i in range(0, len(missing_list), 500):
                chunk = missing_list[i:i + 500]
                session.execute(update(Listing).where(Listing.source_url.in_(chunk)).where(Listing.site_id == self.site_id).values(is_sold_out=True, updated_at=datetime.datetime.now()))
                session.commit()
        session.close()

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(WebikeListingSpider)
    process.start()