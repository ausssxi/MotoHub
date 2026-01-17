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
# 0. 共通ユーティリティの読み込み
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
sys.path.append(parent_dir)

from utils import normalize_shop_name, normalize_address

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

DATABASE_URL = f"mysql+pymysql://{get_env_or_exit('DB_USERNAME')}:{get_env_or_exit('DB_PASSWORD')}@{get_env_or_exit('DB_HOST', 'db')}:{get_env_or_exit('DB_PORT', '3306')}/{get_env_or_exit('DB_DATABASE')}"

engine = create_engine(DATABASE_URL, pool_pre_ping=True)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

class Base(DeclarativeBase):
    pass

class Site(Base):
    __tablename__ = "sites"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(50), unique=True)
    base_url = Column(String(255), nullable=True)

class Shop(Base):
    __tablename__ = "shops"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    name = Column(String(255), nullable=False)
    prefecture = Column(String(20), nullable=True)
    address = Column(String(255), nullable=True)
    phone = Column(String(20), nullable=True)
    website_url = Column(Text, nullable=True)
    rating = Column(Numeric(3, 1), default=0.0)
    business_hours = Column(String(255), nullable=True)
    regular_holiday = Column(String(255), nullable=True)
    image_url = Column(String(255), nullable=True)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

class ShopIdentifier(Base):
    __tablename__ = "shop_identifiers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    shop_id = Column(BigInteger, ForeignKey("shops.id", ondelete="CASCADE"), nullable=False)
    site_id = Column(BigInteger, ForeignKey("sites.id", ondelete="CASCADE"), nullable=False)
    identifier = Column(String(100), nullable=False)
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_shop_site_identifier_uc'),)

# ==========================================
# 2. Scrapy Pipeline (高度な重複チェック実装)
# ==========================================
class DatabasePipeline:
    def open_spider(self, spider):
        self.session = SessionLocal()
        
        # 1. Site登録 (GooBike)
        target_site_name = "GooBike"
        site = self.session.query(Site).filter(Site.name == target_site_name).first()
        if not site:
            site = Site(name=target_site_name, base_url="https://www.goobike.com")
            self.session.add(site)
            self.session.commit()
        self.site_id = site.id

        # 2. 名寄せ用キャッシュの構築
        spider.logger.info("Building cross-site shop matching cache...")
        all_shops = self.session.query(Shop).all()
        all_idents = self.session.query(ShopIdentifier).all()

        # 識別子キャッシュ: {(site_id, identifier): shop_id}
        self.ident_cache = {(i.site_id, i.identifier): i.shop_id for i in all_idents}
        
        # 店名+住所キャッシュ: {normalized_name: [(normalized_address, shop_id), ...]}
        self.name_addr_cache = {}
        for s in all_shops:
            n_norm = normalize_shop_name(s.name)
            a_norm = normalize_address(s.address)
            if n_norm not in self.name_addr_cache:
                self.name_addr_cache[n_norm] = []
            self.name_addr_cache[n_norm].append((a_norm, s.id))

    def process_item(self, item, spider):
        try:
            name = item['name']
            address = item['address']
            identifier = item['identifier']
            shop_id = None

            # 1. サイト固有の識別子でチェック (最優先)
            shop_id = self.ident_cache.get((self.site_id, identifier))

            # 2. 店名 + 住所 で他サイトデータとの重複チェック
            if not shop_id:
                n_norm = normalize_shop_name(name)
                a_norm = normalize_address(address)
                
                # 店名の包含関係をループで回してチェック
                for cached_n_norm, addr_list in self.name_addr_cache.items():
                    # 店名がどちらかの包含関係にあるか
                    if n_norm and cached_n_norm and (n_norm in cached_n_norm or cached_n_norm in n_norm):
                        for cached_a_norm, cached_id in addr_list:
                            # 住所が一致または包含関係にあるか
                            if a_norm and cached_a_norm and (a_norm in cached_a_norm or cached_a_norm in a_norm):
                                shop_id = cached_id
                                break
                    if shop_id: break

            # 3. 登録または更新
            if shop_id:
                shop_record = self.session.query(Shop).get(shop_id)
                if shop_record:
                    shop_record.rating = item['rating']
                    shop_record.business_hours = item['business_hours']
                    shop_record.regular_holiday = item['regular_holiday']
                    if item['image_url']: shop_record.image_url = item['image_url']
            else:
                # 完全新規
                shop_record = Shop(
                    name=name, prefecture=item['prefecture'], address=address,
                    website_url=item['website_url'], rating=item['rating'],
                    business_hours=item['business_hours'], regular_holiday=item['regular_holiday'],
                    image_url=item['image_url']
                )
                self.session.add(shop_record)
                self.session.flush()
                shop_id = shop_record.id
                
                # キャッシュ更新
                n_norm = normalize_shop_name(name)
                if n_norm not in self.name_addr_cache: self.name_addr_cache[n_norm] = []
                self.name_addr_cache[n_norm].append((normalize_address(address), shop_id))

            # 4. 識別子の紐付け (未登録なら)
            if (self.site_id, identifier) not in self.ident_cache:
                self.session.add(ShopIdentifier(shop_id=shop_id, site_id=self.site_id, identifier=identifier))
                self.ident_cache[(self.site_id, identifier)] = shop_id

            self.session.commit()
        except Exception as e:
            self.session.rollback()
            spider.logger.error(f"Error saving shop {item.get('name')}: {e}")
        return item

    def close_spider(self, spider):
        self.session.close()

# ==========================================
# 3. Scrapy Spider
# ==========================================
class GoobikeShopSpider(scrapy.Spider):
    name = "goobike_shop_collector"
    allowed_domains = ["www.goobike.com"]
    start_urls = ["https://www.goobike.com/shop/"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 16,
        'DOWNLOAD_DELAY': 0.5,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {'__main__.DatabasePipeline': 300},
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    }

    def parse(self, response):
        pref_links = response.css(".mapBox li a")
        for link in pref_links:
            url = response.urljoin(link.attrib['href'])
            pref_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', link.xpath("text()").get() or "").strip()
            yield scrapy.Request(url, callback=self.parse_shop_list, meta={'prefecture': pref_name})

    def parse_shop_list(self, response):
        pref_name = response.meta['prefecture']
        for unit in response.css("div.shop"):
            name_link = unit.css(".shop_name a")
            if not name_link: continue
            
            name = name_link.xpath("string(.)").get().strip()
            href = name_link.attrib.get('href', '')
            identifier = re.search(r'client_(\d+)', href).group(1) if href else None

            # 評価点
            rating_text = "".join(unit.css(".review_point *::text").getall())
            rating_match = re.search(r'(\d+\.\d+)', rating_text)
            rating = float(rating_match.group(1)) if rating_match else 0.0

            # 住所・営業時間・定休日
            address = (unit.css(".shop_address::text").get() or "").strip()
            hours = (unit.css(".shop_time::text").get() or "").strip()
            holiday = (unit.css(".shop_holiday::text").get() or "").strip()
            img_url = unit.css(".shop_img img::attr(data-original)").get() or unit.css(".shop_img img::attr(src)").get()

            yield {
                'name': name,
                'prefecture': pref_name,
                'address': address,
                'website_url': response.urljoin(href) if href else None,
                'identifier': identifier,
                'rating': rating,
                'business_hours': hours if hours else None,
                'regular_holiday': holiday if holiday else None,
                'image_url': response.urljoin(img_url) if img_url else None
            }

        next_page = response.css(".pager_next a::attr(href)").get()
        if next_page:
            yield response.follow(next_page, callback=self.parse_shop_list, meta={'prefecture': pref_name})

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(GoobikeShopSpider)
    process.start()