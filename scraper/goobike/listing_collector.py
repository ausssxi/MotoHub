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
from sqlalchemy import create_engine, Column, BigInteger, String, Numeric, Integer, Boolean, Text, JSON, DateTime, update, exists
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
env_path = os.path.join(parent_dir, '..', 'backend', '.env')
if not os.path.exists(env_path):
    env_path = os.path.join(parent_dir, '..', '.env')
load_dotenv(dotenv_path=env_path)

def get_env_or_exit(key, default=None):
    val = os.getenv(key, default)
    if val is None:
        logging.error(f"致命的エラー: 必須の環境変数 '{key}' が設定されていません。")
        sys.exit(1)
    return val

DATABASE_URL = f"mysql+pymysql://{get_env_or_exit('DB_USERNAME')}:{get_env_or_exit('DB_PASSWORD')}@{get_env_or_exit('DB_HOST', 'db')}:{get_env_or_exit('DB_PORT', '3306')}/{get_env_or_exit('DB_DATABASE')}"

# 接続プールを最適化
engine = create_engine(DATABASE_URL, pool_size=20, max_overflow=30, pool_pre_ping=True)
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
# 2. Scrapy Spider
# ==========================================
class GooBikeListingSpider(scrapy.Spider):
    name = "goobike_listings"
    allowed_domains = ["www.goobike.com"]
    start_urls = ["https://www.goobike.com/maker-top/index.html"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 16,     # 帯域に余裕があれば16程度まで上げる
        'DOWNLOAD_DELAY': 0.3,         # 待機時間を少し短縮
        'RANDOMIZE_DOWNLOAD_DELAY': True,
        'COOKIES_ENABLED': True,
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    }

    def __init__(self, *args, **kwargs):
        super(GooBikeListingSpider, self).__init__(*args, **kwargs)
        self.db = SessionLocal()
        site = self.db.query(Site).filter(Site.name == "GooBike").first()
        self.site_id = site.id if site else None

        # キャッシュの高速読み込み（必要なカラムだけに絞ってタプルで取得）
        self.logger.info("Initializing caches...")
        self.model_ident_cache = {
            ident: b_id for ident, b_id in self.db.query(
                BikeModelIdentifier.identifier, BikeModelIdentifier.bike_model_id
            ).filter(BikeModelIdentifier.site_id == self.site_id).all()
        }
        self.shop_cache = {
            ident: s_id for ident, s_id in self.db.query(
                ShopIdentifier.identifier, ShopIdentifier.shop_id
            ).filter(ShopIdentifier.site_id == self.site_id).all()
        }
        
        # 進行中に見つかったURLを保持
        self.found_urls = set()

        dispatcher.connect(self.spider_closed, signals.spider_closed)

    def parse(self, response):
        maker_links = response.css(".makerlist .mj a::attr(href)").getall()
        for href in maker_links:
            yield response.follow(href, callback=self.parse_models)

    def parse_models(self, response):
        bike_list_items = response.css("li.bike_list")
        for item in bike_list_items:
            identifier = item.css("input[name='model']::attr(value)").get()
            model_path = item.css("a::attr(href)").get()
            if identifier and model_path:
                bike_model_id = self.model_ident_cache.get(identifier)
                if bike_model_id:
                    yield response.follow(model_path, callback=self.parse_listings, meta={'bike_model_id': bike_model_id})

    def parse_listings(self, response):
        bike_model_id = response.meta['bike_model_id']
        vehicle_elements = response.css(".bike_sec")
        
        if not vehicle_elements:
            return

        # 1. ページ内の全URLを抽出して一括チェック用のリストを作成
        page_urls = []
        element_map = []
        for v_el in vehicle_elements:
            v_link_el = v_el.css("h4 span a")
            if v_link_el:
                v_url = response.urljoin(v_link_el.attrib.get('href'))
                page_urls.append(v_url)
                element_map.append((v_url, v_el))
                self.found_urls.add(v_url)

        # 2. データベースからこのページ内の既知レコードを一括取得（バルク・フェッチ）
        existing_records = {
            l.source_url: l for l in self.db.query(Listing).filter(
                Listing.source_url.in_(page_urls)
            ).all()
        }

        # 3. 抽出と保存/更新のループ
        for v_url, v_el in element_map:
            try:
                listing_data = self.extract_info(v_el, bike_model_id, response, v_url)
                if not listing_data: continue

                existing = existing_records.get(v_url)

                if existing:
                    self.update_listing(existing, listing_data)
                else:
                    # 他サイトとの重複チェックは依然必要だが、DBへの存在確認をexistsで軽量に
                    shop_id = self.shop_cache.get(listing_data['shop_site_id'])
                    if shop_id:
                        is_dup = self.db.query(exists().where(
                            Listing.shop_id == shop_id,
                            Listing.bike_model_id == listing_data['bike_model_id'],
                            Listing.model_year == listing_data['model_year'],
                            Listing.mileage == listing_data['mileage'],
                            Listing.is_sold_out == False
                        )).scalar()

                        if not is_dup:
                            self.save_listing(v_url, listing_data, shop_id)
                    
            except Exception as e:
                self.logger.error(f"Error at {v_url}: {e}")

        # ページ単位で一括確定
        self.db.commit()

        next_page = response.css("li.next a::attr(href)").get()
        if next_page:
            yield response.follow(next_page, callback=self.parse_listings, meta=response.meta)

    def extract_info(self, v_el, bike_model_id, response, v_url):
        try:
            # 価格解析
            price_txt = "".join(v_el.css("td.num_td *::text").getall()).replace(',', '')
            total_txt = "".join(v_el.css("span.total *::text, .price_total *::text").getall()).replace(',', '')
            
            p_match = re.search(r'(\d+\.?\d*)', price_txt)
            price_val = int(float(p_match.group(1)) * 10000) if p_match else 0
            
            t_match = re.search(r'(\d+\.?\d*)', total_txt)
            total_val = int(float(t_match.group(1)) * 10000) if t_match else None

            # スペック解析
            year, mile = None, None
            has_repair = False
            for li in v_el.css(".cont01 ul li"):
                text = li.xpath("string(.)").get() or ""
                if "年式" in text:
                    y_m = re.search(r'(\d{4})', text)
                    if y_m: year = int(y_m.group(1))
                elif "走行" in text:
                    m_m = re.search(r'(\d+)', text.replace(',', ''))
                    if m_m: mile = int(m_m.group(1))
                elif "修復" in text:
                    has_repair = "あり" in text

            # 店舗ID
            shop_site_id = None
            shop_href = v_el.css(".shop_name a::attr(href)").get()
            if shop_href:
                s_match = re.search(r'client_(\d+)', shop_href)
                if s_match: shop_site_id = s_match.group(1)

            # 画像URL (real-url または src)
            img_el = v_el.css(".bike_img img")
            img_url = img_el.attrib.get('real-url') or img_el.attrib.get('src')
            
            final_image_urls = []
            if img_url:
                full_img_url = response.urljoin(img_url)
                if not any(k in full_img_url.lower() for k in ['loading', '.gif', 'spacer']):
                    final_image_urls = [full_img_url]

            return {
                'bike_model_id': bike_model_id,
                'title': v_el.css("h4 span a::text").get(default="").strip(),
                'price': price_val,
                'total_price': total_val,
                'model_year': year,
                'mileage': mile,
                'has_repair_history': has_repair,
                'shop_site_id': shop_site_id,
                'image_urls': final_image_urls,
            }
        except Exception:
            return None

    def save_listing(self, url, data, shop_id):
        new_listing = Listing(
            bike_model_id=data['bike_model_id'],
            shop_id=shop_id,
            site_id=self.site_id,
            title=data['title'],
            source_url=url,
            price=data['price'],
            total_price=data['total_price'],
            model_year=data['model_year'],
            mileage=data['mileage'],
            image_urls=data['image_urls'], 
            has_repair_history=data['has_repair_history'],
            condition="中古車",
            is_sold_out=False
        )
        self.db.add(new_listing)

    def update_listing(self, existing, data):
        """既存情報の更新"""
        existing.price = data['price']
        existing.total_price = data['total_price']
        existing.is_sold_out = False
        existing.updated_at = datetime.datetime.now()
        
        # 画像補完
        db_imgs = existing.image_urls
        is_db_img_invalid = not db_imgs or not isinstance(db_imgs, list) or len(db_imgs) == 0 or 'loading' in str(db_imgs[0]).lower()

        if is_db_img_invalid and data['image_urls']:
            existing.image_urls = data['image_urls']
            existing.local_image_paths = None

    def spider_closed(self, spider):
        # 完売判定: 今回見つからなかったものを一括で sold_out にする
        self.db.commit()
        self.db.close()

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(GooBikeListingSpider)
    process.start()