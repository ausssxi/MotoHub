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
parent_dir = os.path.dirname(current_dir) # scraper フォルダ
sys.path.append(parent_dir)

from utils import normalize_shop_name, normalize_address, normalize_phone

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

class Base(DeclarativeBase): pass

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
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_shop_site_identifier_uc'),)

# ==========================================
# 2. Scrapy Pipeline (名寄せ & 保存)
# ==========================================
class DatabasePipeline:
    def open_spider(self, spider):
        self.session = SessionLocal()
        
        # Site登録 (Webike)
        target_site_name = "Webike"
        site = self.session.query(Site).filter(Site.name == target_site_name).first()
        if not site:
            site = Site(name=target_site_name, base_url="https://moto.webike.net")
            self.session.add(site)
            self.session.commit()
        self.site_id = site.id

        # 重複チェック用キャッシュ構築
        spider.logger.info("Building cross-site matching cache...")
        all_shops = self.session.query(Shop).all()
        all_idents = self.session.query(ShopIdentifier).all()

        self.ident_cache = {(si.site_id, si.identifier): si.shop_id for si in all_idents}
        self.phone_cache = {}    
        self.name_addr_cache = {} 
        
        for s in all_shops:
            if s.phone:
                p_norm = normalize_phone(s.phone)
                if p_norm: self.phone_cache[p_norm] = s.id
            
            n_norm = normalize_shop_name(s.name)
            a_norm = normalize_address(s.address)
            if n_norm not in self.name_addr_cache:
                self.name_addr_cache[n_norm] = []
            self.name_addr_cache[n_norm].append((a_norm, s.id))

    def process_item(self, item, spider):
        try:
            name = item['name']
            address = item['address']
            phone = item['phone']
            identifier = item['identifier']
            shop_id = None

            # 1. サイト内識別子でチェック
            shop_id = self.ident_cache.get((self.site_id, identifier))

            if not shop_id:
                # 2. 電話番号でチェック
                if phone:
                    p_norm = normalize_phone(phone)
                    shop_id = self.phone_cache.get(p_norm)

                # 3. 店名 + 住所 で名寄せ
                if not shop_id:
                    n_norm = normalize_shop_name(name)
                    a_norm = normalize_address(address)
                    for cached_n_norm, addr_list in self.name_addr_cache.items():
                        if n_norm and cached_n_norm and (n_norm in cached_n_norm or cached_n_norm in n_norm):
                            for cached_a_norm, cached_id in addr_list:
                                if a_norm and cached_a_norm and (a_norm in cached_a_norm or cached_a_norm in a_norm):
                                    shop_id = cached_id
                                    break
                        if shop_id: break

            # 4. 登録または更新
            if shop_id:
                shop_record = self.session.query(Shop).get(shop_id)
                if shop_record:
                    # 情報を最新に更新
                    if phone: shop_record.phone = phone
                    shop_record.business_hours = item['business_hours']
                    shop_record.regular_holiday = item['regular_holiday']
                    shop_record.rating = item['rating']
                    if item['image_url']: shop_record.image_url = item['image_url']
            else:
                # 完全新規
                shop_record = Shop(
                    name=name, prefecture=item['prefecture'], address=address,
                    phone=phone, website_url=item['website_url'], rating=item['rating'],
                    business_hours=item['business_hours'], regular_holiday=item['regular_holiday'],
                    image_url=item['image_url']
                )
                self.session.add(shop_record)
                self.session.flush()
                shop_id = shop_record.id
                
                # キャッシュ更新
                p_norm = normalize_phone(phone)
                if p_norm: self.phone_cache[p_norm] = shop_id
                n_norm = normalize_shop_name(name)
                if n_norm not in self.name_addr_cache: self.name_addr_cache[n_norm] = []
                self.name_addr_cache[n_norm].append((normalize_address(address), shop_id))

            # 5. 識別子の紐付け
            if identifier and (self.site_id, identifier) not in self.ident_cache:
                self.session.add(ShopIdentifier(shop_id=shop_id, site_id=self.site_id, identifier=identifier))
                self.ident_cache[(self.site_id, identifier)] = shop_id

            self.session.commit()
        except Exception as e:
            self.session.rollback()
            spider.logger.error(f"Error saving Webike shop {item.get('name')}: {e}")
        return item

    def close_spider(self, spider):
        self.session.close()

# ==========================================
# 3. Scrapy Spider
# ==========================================
class WebikeShopSpider(scrapy.Spider):
    name = "webike_shop_collector"
    allowed_domains = ["moto.webike.net"]
    start_urls = ["https://moto.webike.net/shop-navi/"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 1.0,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {'__main__.DatabasePipeline': 300},
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    }

    def parse(self, response):
        """トップページから各都道府県リンクを取得"""
        pref_links = response.css('ul.map_todouhuken li a')
        for link in pref_links:
            url = response.urljoin(link.attrib['href'])
            pref_name = link.css('::text').get().strip()
            yield scrapy.Request(url, callback=self.parse_shop_list, meta={'prefecture': pref_name})

    def parse_shop_list(self, response):
        """店舗一覧ページの解析（詳細情報取得対応版）"""
        pref_name = response.meta['prefecture']
        
        # 読み込み中カードを除外
        shop_units = response.css('div.shop-card.size-lg.v2:not(.wait-loading)')

        for unit in shop_units:
            # 基本情報
            name_raw = unit.css('h5.shop-title::text').get()
            if not name_raw: continue
            name = name_raw.strip()

            identifier = unit.attrib.get('data-shop')
            href = unit.css('a.title::attr(href)').get()
            
            # 住所 (アイコンを除去したテキストを取得)
            address = "".join(unit.css('p.shop-address::text').getall()).strip()
            # 電話番号
            phone = "".join(unit.css('p.shop-phone::text').getall()).strip()

            # 営業時間と定休日の抽出
            # ラベル「営業時間」や「定休日」を含む span の後のテキストを取得
            hours = None
            holiday = None
            working_times = unit.css('p.shop-working-time')
            
            for wt in working_times:
                label = wt.css('span.pitin-title::text').get()
                # string(.) でタグの中身を全て平滑化して取得し、ラベル部分を削る
                full_text = wt.xpath('string(.)').get() or ""
                clean_text = full_text.replace(label or "", "").strip()
                
                if label:
                    if "営業時間" in label:
                        hours = clean_text
                    elif "定休日" in label:
                        holiday = clean_text

            # 評価
            rating_text = unit.css('.review-star .point::text').get()
            rating = 0.0
            if rating_text and rating_text.strip() != "-":
                try:
                    rating = float(rating_text.strip())
                except ValueError:
                    rating = 0.0
            
            # 画像
            img_url = unit.css('.shop-thumbnail img::attr(data-src)').get() or unit.css('.shop-thumbnail img::attr(src)').get()

            yield {
                'name': name,
                'prefecture': pref_name,
                'address': address,
                'phone': phone,
                'website_url': response.urljoin(href) if href else None,
                'identifier': identifier,
                'rating': rating,
                'business_hours': hours,
                'regular_holiday': holiday,
                'image_url': response.urljoin(img_url) if img_url else None
            }

        # ページネーション (現在のページ番号 li.current の次の li a を取得)
        next_page = response.css('ul.pagination li.current + li a.paging::attr(href)').get()
        if next_page:
            yield response.follow(next_page, callback=self.parse_shop_list, meta={'prefecture': pref_name})

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(WebikeShopSpider)
    process.start()