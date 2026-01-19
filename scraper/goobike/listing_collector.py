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
env_path = os.path.join(parent_dir, '..', 'backend', '.env')
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
# 2. Scrapy Spider
# ==========================================
class GooBikeListingSpider(scrapy.Spider):
    name = "goobike_listings"
    allowed_domains = ["www.goobike.com"]
    start_urls = ["https://www.goobike.com/maker-top/index.html"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 8,      # 処理速度向上のため並列数を 8 に戻す
        'DOWNLOAD_DELAY': 0.5,         # 待機時間を 0.5 秒に短縮
        'RANDOMIZE_DOWNLOAD_DELAY': True,
        'COOKIES_ENABLED': True,
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    }

    def __init__(self, *args, **kwargs):
        super(GooBikeListingSpider, self).__init__(*args, **kwargs)
        self.db = SessionLocal()
        site = self.db.query(Site).filter(Site.name == "GooBike").first()
        self.site_id = site.id if site else None

        self.model_ident_cache = {i.identifier: i.bike_model_id for i in self.db.query(BikeModelIdentifier).filter(BikeModelIdentifier.site_id == self.site_id).all()}
        self.shop_cache = {i.identifier: i.shop_id for i in self.db.query(ShopIdentifier).filter(ShopIdentifier.site_id == self.site_id).all()}
        
        self.known_urls = {l.source_url for l in self.db.query(Listing.source_url).filter(Listing.site_id == self.site_id, Listing.is_sold_out == False).all()}
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
        
        for v_el in vehicle_elements:
            try:
                v_link_el = v_el.css("h4 span a")
                if not v_link_el: continue
                
                v_url = response.urljoin(v_link_el.attrib.get('href'))
                self.found_urls.add(v_url)

                listing_data = self.extract_info(v_el, bike_model_id, response, v_url)
                if not listing_data: continue

                if v_url in self.known_urls:
                    self.update_listing(v_url, listing_data)
                else:
                    self.save_listing(v_url, listing_data)
                    
            except Exception as e:
                self.logger.error(f"Error at {response.url}: {e}")

        next_page = response.css("li.next a::attr(href)").get()
        if next_page:
            yield response.follow(next_page, callback=self.parse_listings, meta=response.meta)

    def extract_info(self, v_el, bike_model_id, response, v_url):
        """車両1台の情報を抽出"""
        try:
            # 価格解析
            price_txt = "".join(v_el.css("td.num_td *::text").getall()).replace(',', '')
            total_txt = "".join(v_el.css("span.total *::text, .price_total *::text").getall()).replace(',', '')
            
            p_match = re.search(r'(\d+\.?\d*)', price_txt)
            price_val = int(float(p_match.group(1)) * 10000) if p_match else 0
            
            t_match = re.search(r'(\d+\.?\d*)', total_txt)
            total_val = int(float(t_match.group(1)) * 10000) if t_match else None

            # スペック解析
            year, mile, first_reg = None, None, None
            has_repair = False
            for li in v_el.css(".cont01 ul li"):
                label = li.css("span::text").get() or ""
                value = li.css("b::text").get() or ""
                
                if "年式" in label:
                    y_m = re.search(r'(\d{4})', value)
                    if y_m: year = int(y_m.group(1))
                elif "走行" in label:
                    m_m = re.search(r'(\d+)', value.replace(',', ''))
                    if m_m: mile = int(m_m.group(1))
                elif "修復" in label:
                    has_repair = "あり" in value

            # 店舗ID
            shop_site_id = None
            shop_href = v_el.css(".shop_name a::attr(href)").get()
            if shop_href:
                s_match = re.search(r'client_(\d+)', shop_href)
                if s_match: shop_site_id = s_match.group(1)

            # --- Lazy Load 対策：画像URL抽出の強化 ---
            img_el = v_el.css(".bike_img img")
            
            # ソースコード確認に基づき 'real-url' を最優先で取得
            img_url = img_el.attrib.get('real-url') or \
                      v_el.css("noscript img::attr(src)").get() or \
                      img_el.attrib.get('data-original') or \
                      img_el.attrib.get('data-src') or \
                      img_el.attrib.get('data-lazy') or \
                      img_el.attrib.get('src')
            
            final_image_urls = []
            if img_url:
                full_img_url = response.urljoin(img_url)
                
                # 強力なフィルタリング: .gif または loading キーワードを含む場合は拒否
                is_invalid = any(k in full_img_url.lower() for k in ['loading', 'common/img/', '.gif', 'spacer', 'no_image'])
                
                if not is_invalid:
                    final_image_urls = [full_img_url]
                else:
                    # サムネイルからの救済
                    thumb_url = v_el.css(".thumb_list li img::attr(real-url)").get() or \
                                v_el.css(".thumb_list li img::attr(data-original)").get()
                    if thumb_url:
                        final_image_urls = [response.urljoin(thumb_url)]

            return {
                'bike_model_id': bike_model_id,
                'title': v_el.css("h4 span a::text").get(default="").strip(),
                'price': price_val,
                'total_price': total_val,
                'model_year': year,
                'mileage': mile,
                'first_registration': first_reg,
                'has_repair_history': has_repair,
                'shop_site_id': shop_site_id,
                'image_urls': final_image_urls,
            }
        except Exception as e:
            self.logger.warning(f"Parse error for {v_url}: {e}")
            return None

    def save_listing(self, url, data):
        """新規保存"""
        shop_id = self.shop_cache.get(data['shop_site_id'])
        if not shop_id: return

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
        self.db.commit()
        self.known_urls.add(url)

    def is_invalid_image(self, urls):
        """保存されている画像URLが不適切（Loading画像など）かどうか判定"""
        if not urls or not isinstance(urls, list) or len(urls) == 0:
            return True
        first_url = str(urls[0]).lower()
        return any(k in first_url for k in ['loading', '.gif', 'spacer', 'common/img', 'no_image'])

    def update_listing(self, url, data):
        """既存情報の更新 (不適切な画像URLの強制クリアを含む)"""
        existing = self.db.query(Listing).filter(Listing.source_url == url).first()
        if not existing: return

        update_values = {
            'price': data['price'], 
            'total_price': data['total_price'], 
            'is_sold_out': False,
            'updated_at': datetime.datetime.now()
        }

        # --- 画像修復ロジック ---
        if self.is_invalid_image(existing.image_urls):
            if not self.is_invalid_image(data['image_urls']):
                update_values['image_urls'] = data['image_urls']
                update_values['local_image_paths'] = None
            else:
                update_values['image_urls'] = None
                update_values['local_image_paths'] = None

        self.db.execute(update(Listing).where(Listing.source_url == url).values(**update_values))
        self.db.commit()

    def spider_closed(self, spider):
        missing_urls = self.known_urls - self.found_urls
        if missing_urls:
            missing_list = list(missing_urls)
            chunk_size = 500
            for i in range(0, len(missing_list), chunk_size):
                chunk = missing_list[i:i + chunk_size]
                self.db.execute(
                    update(Listing)
                    .where(Listing.source_url.in_(chunk))
                    .where(Listing.site_id == self.site_id)
                    .values(is_sold_out=True, updated_at=datetime.datetime.now())
                )
                self.db.commit()
        self.db.close()

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(GooBikeListingSpider)
    process.start()