import scrapy
from scrapy.crawler import CrawlerProcess
from scrapy.signalmanager import dispatcher
from scrapy import signals
import os
import re
import datetime
import sys
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, BigInteger, String, Numeric, Integer, Boolean, Text, JSON, DateTime, update, or_
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# ==========================================
# 1. 環境設定 & データベース定義
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
env_path = os.path.join(current_dir, '..', '..', '.env')
load_dotenv(dotenv_path=env_path)

if not os.getenv("DB_DATABASE"):
    load_dotenv()

def get_env_or_exit(key, default=None, required=True):
    val = os.getenv(key, default)
    if required and val is None:
        print(f"致命的エラー: 必須の環境変数 '{key}' が設定されていません。")
        sys.exit(1)
    return val

# データベース接続設定
DB_USER = get_env_or_exit("DB_USERNAME")
DB_PASS = get_env_or_exit("DB_PASSWORD")
DB_NAME = get_env_or_exit("DB_DATABASE")
DB_HOST = get_env_or_exit("DB_HOST", default="db")
DB_PORT = get_env_or_exit("DB_PORT", default="3306")

DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}"

engine = create_engine(DATABASE_URL)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

class Base(DeclarativeBase):
    pass

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
# 2. Scrapy Spider
# ==========================================
class GooBikeListingSpider(scrapy.Spider):
    name = "goobike_listings"
    allowed_domains = ["www.goobike.com"]
    start_urls = ["https://www.goobike.com/maker-top/index.html"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 32,
        'DOWNLOAD_DELAY': 0.3,
        'COOKIES_ENABLED': False,
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    }

    def __init__(self, *args, **kwargs):
        super(GooBikeListingSpider, self).__init__(*args, **kwargs)
        self.db = SessionLocal()
        site = self.db.query(Site).filter(Site.name == "GooBike").first()
        self.site_id = site.id if site else None

        # 1. 外部キー紐付け用のキャッシュ構築
        self.model_ident_cache = {i.identifier: i.bike_model_id for i in self.db.query(BikeModelIdentifier).filter(BikeModelIdentifier.site_id == self.site_id).all()}
        self.shop_cache = {i.identifier: i.shop_id for i in self.db.query(ShopIdentifier).filter(ShopIdentifier.site_id == self.site_id).all()}
        
        # 2. 既存URLの取得（掲載終了判定用）
        self.known_urls = {l.source_url for l in self.db.query(Listing.source_url).filter(Listing.site_id == self.site_id, Listing.is_sold_out == False).all()}
        self.found_urls = set()

        # Spider終了時に完売判定を実行するためのシグナル登録
        dispatcher.connect(self.spider_closed, signals.spider_closed)

    def parse(self, response):
        """メーカー一覧から各メーカーページへ"""
        maker_links = response.css(".makerlist .mj a::attr(href)").getall()
        for href in maker_links:
            yield response.follow(href, callback=self.parse_models)

    def parse_models(self, response):
        """メーカー内車種一覧から各車種の出品一覧へ"""
        bike_list_items = response.css("li.bike_list")
        for item in bike_list_items:
            identifier = item.css("input[name='model']::attr(value)").get()
            model_path = item.css("a::attr(href)").get()
            if identifier and model_path:
                bike_model_id = self.model_ident_cache.get(identifier)
                if bike_model_id:
                    yield response.follow(model_path, callback=self.parse_listings, meta={'bike_model_id': bike_model_id})

    def parse_listings(self, response):
        """出品一覧ページから車両データを抽出"""
        bike_model_id = response.meta['bike_model_id']
        vehicle_elements = response.css(".bike_sec")
        
        for v_el in vehicle_elements:
            v_link_el = v_el.css("h4 span a")
            if not v_link_el: continue
            
            v_url = response.urljoin(v_link_el.css("::attr(href)").get())
            self.found_urls.add(v_url)

            # 情報の抽出
            listing_data = self.extract_info(v_el, bike_model_id, response, v_url)

            # 更新または新規保存
            if v_url in self.known_urls:
                self.update_listing(v_url, listing_data)
            else:
                self.save_listing(v_url, listing_data)

        # ページネーション処理
        next_page = response.css(".pager_next a::attr(href)").get()
        if next_page:
            yield response.follow(next_page, callback=self.parse_listings, meta=response.meta)

    def extract_info(self, v_el, bike_model_id, response, v_url):
        """車両1台の情報を抽出"""
        # 価格（万円を数値へ変換）
        price_all = "".join(v_el.css("td.num_td *::text").getall()).replace(',', '')
        total_all = "".join(v_el.css("span.total *::text, .price_total *::text").getall()).replace(',', '')
        
        price_val = int(float(re.search(r'(\d+\.?\d*)', price_all).group(1)) * 10000) if re.search(r'(\d+\.?\d*)', price_all) else 0
        total_val = int(float(re.search(r'(\d+\.?\d*)', total_all).group(1)) * 10000) if re.search(r'(\d+\.?\d*)', total_all) else None

        # スペック情報
        year, mile, first_reg = None, None, None
        has_repair = False
        condition = "中古車"
        
        # リスト項目（年式、走行距離、修復歴など）の抽出
        for li in v_el.css(".cont01 ul li"):
            label = li.css("span::text").get()
            value = li.css("b::text").get()
            
            if not label or not value:
                continue
                
            label = label.strip()
            value = value.strip()

            if "モデル年式" in label:
                y_m = re.search(r'(\d{4})', value)
                if y_m:
                    year = int(y_m.group(1))
            elif "初度登録" in label:
                first_reg = value
            elif "走行距離" in label:
                m_m = re.search(r'(\d+)', value.replace(',', ''))
                if m_m:
                    mile = int(m_m.group(1))
            elif "修復歴" in label:
                has_repair = True if "あり" in value else False

        # 店舗ID取得
        shop_site_id = None
        shop_href = v_el.css(".shop_name a::attr(href)").get()
        if shop_href:
            s_match = re.search(r'client_(\d+)', shop_href)
            if s_match: shop_site_id = s_match.group(1)

        # 画像URL
        img_url = v_el.css(".bike_img img::attr(real-url)").get() or v_el.css(".bike_img img::attr(src)").get()

        return {
            'bike_model_id': bike_model_id,
            'title': v_el.css("h4 span a::text").get().strip() if v_el.css("h4 span a::text").get() else "",
            'price': price_val,
            'total_price': total_val,
            'model_year': year,
            'mileage': mile,
            'first_registration': first_reg,
            'has_repair_history': has_repair,
            'shop_site_id': shop_site_id,
            'image_urls': [response.urljoin(img_url)] if img_url else [],
            'condition': condition,
        }

    def save_listing(self, url, data):
        """新規データの保存"""
        try:
            shop_id = self.shop_cache.get(data['shop_site_id'])
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
                first_registration=data['first_registration'],
                image_urls=data['image_urls'],
                has_repair_history=data['has_repair_history'],
                condition=data['condition'],
                is_sold_out=False
            )
            self.db.add(new_listing)
            self.db.commit()
            self.known_urls.add(url)
            self.logger.info(f"New Listing: {data['title']}")
        except Exception as e:
            self.db.rollback()
            self.logger.error(f"Save error: {url} - {e}")

    def update_listing(self, url, data):
        """価格などの既存データの更新"""
        try:
            self.db.execute(
                update(Listing).where(Listing.source_url == url)
                .values(
                    price=data['price'], 
                    total_price=data['total_price'], 
                    updated_at=datetime.datetime.now()
                )
            )
            self.db.commit()
        except Exception as e:
            self.db.rollback()
            self.logger.error(f"Update error: {url} - {e}")

    def spider_closed(self, spider):
        """巡回終了時に、DBにあるが今回見つからなかったURLを完売（is_sold_out=True）にする"""
        print("\n掲載終了判定を実行中...")
        missing_urls = self.known_urls - self.found_urls
        if missing_urls:
            missing_list = list(missing_urls)
            chunk_size = 100
            for i in range(0, len(missing_list), chunk_size):
                chunk = missing_list[i:i + chunk_size]
                self.db.execute(
                    update(Listing)
                    .where(Listing.source_url.in_(chunk))
                    .where(Listing.site_id == self.site_id)
                    .values(is_sold_out=True, updated_at=datetime.datetime.now())
                )
            self.db.commit()
            print(f"  -> {len(missing_urls)} 件の完売（掲載終了）処理を完了しました。")
        self.db.close()

# ==========================================
# 3. 実行ブロック
# ==========================================
if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(GooBikeListingSpider)
    process.start()