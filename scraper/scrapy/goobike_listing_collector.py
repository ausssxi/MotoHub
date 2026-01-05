import scrapy
from scrapy.crawler import CrawlerProcess
from scrapy.signalmanager import dispatcher
from scrapy import signals
import os
import re
import datetime
import sys
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, BigInteger, String, Numeric, Integer, Boolean, Text, JSON, DateTime, update
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# 1. 環境変数の読み込み
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
    image_urls = Column(JSON, nullable=True)
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

# 2. Scrapy Spiderの定義
class GooBikeListingSpider(scrapy.Spider):
    name = "goobike_listings"
    allowed_domains = ["www.goobike.com"]
    start_urls = ["https://www.goobike.com/maker-top/index.html"]

    custom_settings = {
        'REQUEST_FINGERPRINTER_IMPLEMENTATION': '2.7',
        'CONCURRENT_REQUESTS': 16,
        'DOWNLOAD_DELAY': 0.5,
        'COOKIES_ENABLED': False,
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    }

    def __init__(self, *args, **kwargs):
        super(GooBikeListingSpider, self).__init__(*args, **kwargs)
        self.db = SessionLocal()
        
        # サイトIDの取得
        site = self.db.query(Site).filter(Site.name == "GooBike").first()
        self.site_id = site.id if site else None

        # キャッシュの構築
        self.model_ident_cache = {i.identifier: i.bike_model_id for i in self.db.query(BikeModelIdentifier).filter(BikeModelIdentifier.site_id == self.site_id).all()}
        self.shop_cache = {i.identifier: i.shop_id for i in self.db.query(ShopIdentifier).filter(ShopIdentifier.site_id == self.site_id).all()}
        
        # DBにある「販売中」のURLをロード
        self.known_urls = {l.source_url for l in self.db.query(Listing.source_url).filter(Listing.site_id == self.site_id, Listing.is_sold_out == False).all()}
        self.found_urls = set()

        # 終了時処理の登録
        dispatcher.connect(self.spider_closed, signals.spider_closed)

    def parse(self, response):
        """メーカー一覧から各メーカーURLを取得"""
        maker_links = response.css(".makerlist .mj a::attr(href)").getall()
        for href in maker_links:
            yield response.follow(href, callback=self.parse_models)

    def parse_models(self, response):
        """メーカー内の車種一覧から、各車種の在庫ページURLを取得"""
        bike_list_items = response.css("li.bike_list")
        for item in bike_list_items:
            identifier = item.css("input[name='model']::attr(value)").get()
            model_path = item.css("a::attr(href)").get()

            if identifier and model_path:
                bike_model_id = self.model_ident_cache.get(identifier)
                if bike_model_id:
                    yield response.follow(
                        model_path, 
                        callback=self.parse_listings, 
                        meta={'bike_model_id': bike_model_id}
                    )

    def parse_listings(self, response):
        """車両一覧ページから各車両のデータを抽出"""
        bike_model_id = response.meta['bike_model_id']
        vehicle_elements = response.css(".bike_sec")
        
        for v_el in vehicle_elements:
            try:
                # URLとタイトル
                v_link_el = v_el.css("h4 span a")
                if not v_link_el: continue
                v_url = response.urljoin(v_link_el.css("::attr(href)").get())
                v_title = v_link_el.css("::text").get().strip()

                # 今回見つかったURLとして記録
                self.found_urls.add(v_url)

                # 重複スキップ
                if v_url in self.known_urls:
                    continue

                # --- 修正点: 価格の抽出 (子要素を含めたすべてのテキストを取得) ---
                price_val, total_price_val = 0, None
                
                # 本体価格
                price_all_text = "".join(v_el.css("td.num_td *::text").getall()).replace(',', '')
                p_match = re.search(r'(\d+\.?\d*)', price_all_text)
                if p_match:
                    price_val = int(float(p_match.group(1)) * 10000)

                # 支払総額 (span.total や .price_total クラスを探す)
                total_all_text = "".join(v_el.css("span.total *::text, .price_total *::text").getall()).replace(',', '')
                t_match = re.search(r'(\d+\.?\d*)', total_all_text)
                if t_match:
                    total_price_val = int(float(t_match.group(1)) * 10000)

                # --- 修正点: 年式・走行距離 (li内の全テキストを検索対象にする) ---
                year, mile = None, None
                spec_items = v_el.css(".cont01 ul li")
                for item in spec_items:
                    li_text = "".join(item.css("*::text").getall())
                    if "年式" in li_text:
                        y_m = re.search(r'(\d{4})', li_text)
                        if y_m: year = int(y_m.group(1))
                    elif "走行" in li_text:
                        # カンマを除去し、数字部分を抽出
                        m_m = re.search(r'(\d+)', li_text.replace(',', ''))
                        if m_m: mile = int(m_m.group(1))

                # 画像 (real-url属性を優先)
                img_url = v_el.css(".bike_img img::attr(real-url)").get() or v_el.css(".bike_img img::attr(src)").get()
                images = [response.urljoin(img_url)] if img_url else []

                # 販売店特定
                shop_id = None
                shop_href = v_el.css(".shop_name a::attr(href)").get()
                if shop_href:
                    s_match = re.search(r'client_(\d+)', shop_href)
                    if s_match:
                        shop_id = self.shop_cache.get(s_match.group(1))

                # 新規保存
                new_listing = Listing(
                    bike_model_id=bike_model_id,
                    shop_id=shop_id,
                    site_id=self.site_id,
                    title=v_title,
                    source_url=v_url,
                    price=price_val,
                    total_price=total_price_val,
                    model_year=year,
                    mileage=mile,
                    image_urls=images,
                    is_sold_out=False
                )
                self.db.add(new_listing)
                self.db.commit()
                self.known_urls.add(v_url)

            except Exception as e:
                self.db.rollback()
                self.logger.error(f"車両保存エラー: {e}")

    def spider_closed(self, spider):
        """スパイダー終了時に掲載終了（完売）を判定"""
        print("\n掲載終了車両の判定を行っています...")
        
        missing_urls = self.known_urls - self.found_urls
        
        if missing_urls:
            missing_list = list(missing_urls)
            chunk_size = 100
            total_updated = 0
            
            for i in range(0, len(missing_list), chunk_size):
                chunk = missing_list[i:i + chunk_size]
                self.db.execute(
                    update(Listing)
                    .where(Listing.source_url.in_(chunk))
                    .where(Listing.site_id == self.site_id)
                    .values(is_sold_out=True, updated_at=datetime.datetime.now())
                )
                total_updated += len(chunk)
            
            self.db.commit()
            print(f"  -> {total_updated} 件を「掲載終了（完売）」に更新しました。")
        else:
            print("  -> 新たな掲載終了車両はありません。")

        self.db.close()

# 実行用
def main():
    print("GooBike出品情報コレクター (Scrapy版) を起動しています...")
    process = CrawlerProcess()
    process.crawl(GooBikeListingSpider)
    process.start()
    print("すべての同期処理が完了しました。")

if __name__ == "__main__":
    main()