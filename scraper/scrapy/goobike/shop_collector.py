import os
import re
import sys
import datetime
import logging
import scrapy
from scrapy.crawler import CrawlerProcess
from sqlalchemy import create_engine, Column, BigInteger, String, Text, DateTime, ForeignKey, UniqueConstraint, Numeric
from sqlalchemy.orm import DeclarativeBase, sessionmaker
from sqlalchemy.exc import IntegrityError
from dotenv import load_dotenv

# ==========================================
# 1. データベース設定 & モデル定義
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
# プロジェクトルートの .env を読み込む
env_path = os.path.join(current_dir, '..', '..', '.env')
load_dotenv(dotenv_path=env_path)

def get_env_or_exit(key, default=None, required=True):
    val = os.getenv(key, default)
    if required and val is None:
        logging.error(f"致命的エラー: 必須の環境変数 '{key}' が設定されていません。")
        sys.exit(1)
    return val

DB_USER = get_env_or_exit("DB_USERNAME")
DB_PASS = get_env_or_exit("DB_PASSWORD")
DB_NAME = get_env_or_exit("DB_DATABASE")
DB_HOST = get_env_or_exit("DB_HOST", default="db")
DB_PORT = get_env_or_exit("DB_PORT", default="3306")

DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}"

class Base(DeclarativeBase):
    pass

class Site(Base):
    __tablename__ = "sites"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(50), unique=True)

class Shop(Base):
    __tablename__ = "shops"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    name = Column(String(255), nullable=False)
    prefecture = Column(String(20), nullable=True)
    address = Column(String(255), nullable=True)
    phone = Column(String(20), nullable=True)
    website_url = Column(Text, nullable=True)
    
    # 追加されたスクレイピング項目
    rating = Column(Numeric(3, 1), default=0.0)
    business_hours = Column(String(255), nullable=True)
    regular_holiday = Column(String(255), nullable=True)
    image_url = Column(String(255), nullable=True)
    local_image_path = Column(String(255), nullable=True)
    
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

class ShopIdentifier(Base):
    __tablename__ = "shop_identifiers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    shop_id = Column(BigInteger, ForeignKey("shops.id", ondelete="CASCADE"), nullable=False)
    site_id = Column(BigInteger, ForeignKey("sites.id", ondelete="CASCADE"), nullable=False)
    identifier = Column(String(100), nullable=False)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)
    
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_shop_site_identifier_uc'),)

# ==========================================
# 2. Scrapy Pipeline (DB保存処理)
# ==========================================
class DatabasePipeline:
    def open_spider(self, spider):
        self.engine = create_engine(DATABASE_URL)
        self.Session = sessionmaker(bind=self.engine)
        self.session = self.Session()
        
        # サイトIDの取得
        site = self.session.query(Site).filter(Site.name == "GooBike").first()
        if not site:
            spider.logger.error("GooBike site not found in DB.")
            raise Exception("GooBike site not found in DB.")
        self.site_id = site.id

    def process_item(self, item, spider):
        try:
            name = item['name']
            address = item['address'] or ''
            identifier = item['identifier']

            # 1. 識別子から既存店舗を探す
            shop_id = None
            if identifier:
                existing_ident = self.session.query(ShopIdentifier).filter(
                    ShopIdentifier.site_id == self.site_id,
                    ShopIdentifier.identifier == identifier
                ).first()
                if existing_ident:
                    shop_id = existing_ident.shop_id

            # 2. 登録または更新
            if not shop_id:
                # 新規登録
                shop_record = Shop(
                    name=name,
                    prefecture=item['prefecture'],
                    address=address,
                    website_url=item['website_url'],
                    rating=item['rating'],
                    business_hours=item['business_hours'],
                    regular_holiday=item['regular_holiday'],
                    image_url=item['image_url']
                )
                self.session.add(shop_record)
                self.session.flush()
                shop_id = shop_record.id
            else:
                # 既存情報の更新（評価や営業時間は変動するため）
                shop_record = self.session.query(Shop).get(shop_id)
                shop_record.rating = item['rating']
                shop_record.business_hours = item['business_hours']
                shop_record.regular_holiday = item['regular_holiday']
                if item['image_url']:
                    shop_record.image_url = item['image_url']
                shop_record.updated_at = datetime.datetime.now()

            # 3. 識別子の紐付け
            if identifier:
                ident_exists = self.session.query(ShopIdentifier).filter(
                    ShopIdentifier.site_id == self.site_id,
                    ShopIdentifier.identifier == identifier
                ).first()
                if not ident_exists:
                    self.session.add(ShopIdentifier(
                        shop_id=shop_id, 
                        site_id=self.site_id, 
                        identifier=identifier
                    ))
            
            self.session.commit()
        except Exception as e:
            self.session.rollback()
            spider.logger.error(f"Error saving item: {e}")
        return item

    def close_spider(self, spider):
        self.session.close()

# ==========================================
# 3. Scrapy Spider (巡回ロジック)
# ==========================================
class GoobikeShopSpider(scrapy.Spider):
    name = "goobike_shop_spider"
    allowed_domains = ["www.goobike.com"]
    start_urls = ["https://www.goobike.com/shop/"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 16,
        'DOWNLOAD_DELAY': 0.5,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {
            '__main__.DatabasePipeline': 300,
        },
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    }

    def parse(self, response):
        """都道府県一覧から各エリアへ"""
        pref_links = response.css(".mapBox li a")
        for link in pref_links:
            url = response.urljoin(link.attrib['href'])
            raw_name = link.xpath("text()").get()
            pref_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', raw_name).strip()
            yield scrapy.Request(url, callback=self.parse_shop_list, meta={'prefecture': pref_name})

    def parse_shop_list(self, response):
        """店舗一覧ページの解析"""
        pref_name = response.meta['prefecture']
        shop_units = response.css("div.shop")

        for unit in shop_units:
            # 1. 店名・URL・識別子
            name_link = unit.css(".shop_name a")
            if not name_link:
                continue
            
            name = name_link.xpath("string(.)").get().strip()
            href = name_link.attrib.get('href', '')
            identifier = re.search(r'client_(\d+)', href).group(1) if href else None

            # 2. 評価点 (例: 5.0)
            # 文字列を結合して数値のみ抽出
            rating_text = "".join(unit.css(".review_point *::text").getall())
            rating_match = re.search(r'(\d+\.\d+)', rating_text)
            rating = float(rating_match.group(1)) if rating_match else 0.0

            # 3. 住所・営業時間・定休日
            address = unit.css(".shop_address::text").get()
            hours = unit.css(".shop_time::text").get()
            holiday = unit.css(".shop_holiday::text").get()

            # 4. 店舗画像URL (data-original 優先)
            img_url = unit.css(".shop_img img::attr(data-original)").get() or unit.css(".shop_img img::attr(src)").get()

            yield {
                'name': name,
                'prefecture': pref_name,
                'address': address.strip() if address else "",
                'website_url': response.urljoin(href) if href else None,
                'identifier': identifier,
                'rating': rating,
                'business_hours': hours.strip() if hours else None,
                'regular_holiday': holiday.strip() if holiday else None,
                'image_url': response.urljoin(img_url) if img_url else None
            }

        # 次のページへ
        next_page = response.css(".pager_next a::attr(href)").get()
        if next_page:
            yield response.follow(next_page, callback=self.parse_shop_list, meta={'prefecture': pref_name})

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(GoobikeShopSpider)
    process.start()