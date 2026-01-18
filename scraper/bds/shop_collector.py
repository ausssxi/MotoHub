import os
import re
import sys
import datetime
import logging
import scrapy
from scrapy.crawler import CrawlerProcess
from sqlalchemy import create_engine, Column, BigInteger, String, Text, DateTime, ForeignKey, UniqueConstraint, Numeric, or_
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
# 2. Scrapy Pipeline (名寄せロジックの改善)
# ==========================================
class DatabasePipeline:
    def open_spider(self, spider):
        self.session = SessionLocal()
        
        target_site_name = "BDS"
        site = self.session.query(Site).filter(Site.name == target_site_name).first()
        if not site:
            site = Site(name=target_site_name, base_url="https://www.bds-bikesensor.net")
            self.session.add(site)
            self.session.commit()
        self.site_id = site.id

        spider.logger.info("名寄せ用キャッシュを構築中...")
        all_shops = self.session.query(Shop).all()
        all_idents = self.session.query(ShopIdentifier).all()

        self.phone_cache = {}    
        self.name_addr_cache = {} 
        self.ident_cache = {(si.site_id, si.identifier): si.shop_id for si in all_idents}

        for s in all_shops:
            if s.phone:
                p_norm = normalize_phone(s.phone)
                if p_norm: self.phone_cache[p_norm] = s.id
            
            n_norm = normalize_shop_name(s.name)
            a_norm = normalize_address(s.address)
            if n_norm:
                if n_norm not in self.name_addr_cache:
                    self.name_addr_cache[n_norm] = []
                self.name_addr_cache[n_norm].append((a_norm, s.id))

    def process_item(self, item, spider):
        try:
            name = item['name']
            address = item['address'] or ''
            phone = item['phone']
            identifier = item['identifier']
            shop_id = None

            # 1. BDSの識別子で既に登録済みかチェック
            shop_id = self.ident_cache.get((self.site_id, identifier))

            if not shop_id:
                # 2. 電話番号で他サイト(GooBike等)との重複をチェック
                if phone:
                    p_norm = normalize_phone(phone)
                    if p_norm:
                        shop_id = self.phone_cache.get(p_norm)

                # 3. 店名 + 住所 でチェック（住所が取れている場合のみ）
                if not shop_id and address:
                    n_norm = normalize_shop_name(name)
                    a_norm = normalize_address(address)
                    
                    # 店名が完全一致するものを探す（in判定はやめて厳密にする）
                    addr_list = self.name_addr_cache.get(n_norm, [])
                    for cached_a_norm, cached_id in addr_list:
                        # 住所も完全一致、またはどちらかが包含
                        if a_norm and cached_a_norm and (a_norm in cached_a_norm or cached_a_norm in a_norm):
                            shop_id = cached_id
                            break

            if shop_id:
                # 既存ショップの情報を更新（新規登録ではない）
                shop_record = self.session.query(Shop).get(shop_id)
                if shop_record:
                    if not shop_record.phone and phone: shop_record.phone = phone
                    if not shop_record.address and address: shop_record.address = address
                    shop_record.business_hours = item['business_hours']
                    shop_record.regular_holiday = item['regular_holiday']
                    if item['image_url']: shop_record.image_url = item['image_url']
            else:
                # 【新規登録】
                shop_record = Shop(
                    name=name, prefecture=item['prefecture'], address=address,
                    phone=phone, website_url=item['website_url'],
                    business_hours=item['business_hours'], regular_holiday=item['regular_holiday'],
                    image_url=item['image_url']
                )
                self.session.add(shop_record)
                self.session.flush() # ID確定
                shop_id = shop_record.id
                
                # キャッシュを更新して次のループでの二重登録を防ぐ
                p_norm = normalize_phone(phone)
                if p_norm: self.phone_cache[p_norm] = shop_id
                n_norm = normalize_shop_name(name)
                if n_norm:
                    if n_norm not in self.name_addr_cache: self.name_addr_cache[n_norm] = []
                    self.name_addr_cache[n_norm].append((normalize_address(address), shop_id))

            # BDS識別子とショップIDを紐付け
            if identifier and (self.site_id, identifier) not in self.ident_cache:
                self.session.add(ShopIdentifier(shop_id=shop_id, site_id=self.site_id, identifier=identifier))
                self.ident_cache[(self.site_id, identifier)] = shop_id
            
            self.session.commit()
        except Exception as e:
            self.session.rollback()
            spider.logger.error(f"Error saving BDS shop {item.get('name')}: {e}")
        return item

    def close_spider(self, spider):
        self.session.close()

# ==========================================
# 3. Scrapy Spider (セレクタの改善)
# ==========================================
class BdsShopSpider(scrapy.Spider):
    name = "bds_shop_collector"
    allowed_domains = ["www.bds-bikesensor.net"]

    PREF_MAP = {
        "01": "北海道", "02": "青森県", "03": "岩手県", "04": "宮城県", "05": "秋田県", "06": "山形県", "07": "福島県",
        "08": "茨城県", "09": "栃木県", "10": "群馬県", "11": "埼玉県", "12": "千葉県", "13": "東京都", "14": "神奈川県",
        "15": "新潟県", "16": "富山県", "17": "石川県", "18": "福井県", "19": "山梨県", "20": "長野県", "21": "岐阜県",
        "22": "静岡県", "23": "愛知県", "24": "三重県", "25": "滋賀県", "26": "京都府", "27": "大阪府", "28": "兵庫県",
        "29": "奈良県", "30": "和歌山県", "31": "鳥取県", "32": "島根県", "33": "岡山県", "34": "広島県", "35": "山口県",
        "36": "徳島県", "37": "香川県", "38": "愛媛県", "39": "高知県", "40": "福岡県", "41": "佐賀県", "42": "長崎県",
        "43": "熊本県", "44": "大分県", "45": "宮崎県", "46": "鹿児島県", "47": "沖縄県"
    }
    
    def start_requests(self):
        base_url = "https://www.bds-bikesensor.net/shop?prefectureCodes%5B%5D="
        for code, name in self.PREF_MAP.items():
            yield scrapy.Request(base_url + code, callback=self.parse_shop_list, meta={'prefecture': name})

    custom_settings = {
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 1.0,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {'__main__.DatabasePipeline': 300},
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    }

    def parse_shop_list(self, response):
        pref_name = response.meta['prefecture']
        shop_units = response.css("li.c-search_block_list_item.type_shop")

        for unit in shop_units:
            name_link = unit.css(".c-search_block_shop_title01 a")
            if not name_link: continue
            
            name = name_link.xpath("string(.)").get().strip()
            href = name_link.attrib.get('href', '')
            # URLから識別子を抜く (例: /shop/client/12345)
            identifier = re.search(r'client/(\d+)', href).group(1) if href else None

            address, hours, holiday, phone = "", "", "", ""
            # テーブルの各行をループ
            rows = unit.css(".c-search_block_shop-info_table table tr")
            for row in rows:
                # string(.) を使うことで、thやtdの中にアイコンやspanが混ざっていてもテキストを全て連結して取得
                th_txt = row.xpath("string(th)").get() or ""
                td_txt = row.xpath("string(td)").get() or ""
                
                if "住所" in th_txt: address = td_txt.strip()
                elif "営業時間" in th_txt: hours = td_txt.strip()
                elif "定休日" in th_txt: holiday = td_txt.strip()
                elif "電話番号" in th_txt: phone = td_txt.strip()

            # 画像は data-src または src から取得
            img_url = unit.css("figure.c-delay_load::attr(data-src)").get() or unit.css("figure.c-delay_load img::attr(src)").get()

            if name:
                yield {
                    'name': name,
                    'prefecture': pref_name,
                    'address': address,
                    'phone': phone,
                    'website_url': response.urljoin(href) if href else None,
                    'identifier': identifier,
                    'business_hours': hours,
                    'regular_holiday': holiday,
                    'image_url': response.urljoin(img_url) if img_url else None
                }

        # ページネーション
        next_page = response.css("div.c-pager a.c-btn_next::attr(href)").get()
        if next_page:
            yield response.follow(next_page, callback=self.parse_shop_list, meta={'prefecture': pref_name})

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(BdsShopSpider)
    process.start()