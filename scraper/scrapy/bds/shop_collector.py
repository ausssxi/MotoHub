import os
import re
import sys
import datetime
import logging
import scrapy
import unicodedata
from scrapy.crawler import CrawlerProcess
from sqlalchemy import create_engine, Column, BigInteger, String, Text, DateTime, ForeignKey, UniqueConstraint, Numeric
from sqlalchemy.orm import DeclarativeBase, sessionmaker
from sqlalchemy.exc import IntegrityError
from dotenv import load_dotenv

# ==========================================
# 1. データベース設定 & モデル定義
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
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

def normalize_text(text: str) -> str:
    """日本の住所表記のゆれを吸収する高度な正規化"""
    if not text:
        return ""
    text = unicodedata.normalize('NFKC', text)
    text = re.sub(r'\s+', '', text).lower()
    text = re.sub(r'[丁目|番地|番|号]', '-', text)
    text = re.sub(r'[－ー−―‐－-]', '-', text)
    text = re.sub(r'-+', '-', text)
    return text.strip('-')

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
# 2. Scrapy Pipeline (DB保存 & 名寄せ処理)
# ==========================================
class DatabasePipeline:
    def open_spider(self, spider):
        self.engine = create_engine(DATABASE_URL)
        self.Session = sessionmaker(bind=self.engine)
        self.session = self.Session()
        
        site = self.session.query(Site).filter(Site.name == "BDS").first()
        if not site:
            spider.logger.error("sitesテーブルに 'BDS' が登録されていません。")
            raise Exception("BDS site not found in DB.")
        self.site_id = site.id

        self.shop_cache = {}
        for s in self.session.query(Shop).all():
            n_name = normalize_text(s.name)
            n_addr = normalize_text(s.address)
            if n_name not in self.shop_cache:
                self.shop_cache[n_name] = []
            self.shop_cache[n_name].append((n_addr, s.id))
        
        idents = self.session.query(ShopIdentifier.site_id, ShopIdentifier.identifier, ShopIdentifier.shop_id).all()
        self.ident_cache = {(si.site_id, si.identifier): si.shop_id for si in idents}

    def process_item(self, item, spider):
        try:
            name = item['name']
            address = item['address'] or ''
            identifier = item['identifier']
            norm_name = normalize_text(name)
            norm_address = normalize_text(address)

            shop_id = self.ident_cache.get((self.site_id, identifier))
            if not shop_id:
                candidates = self.shop_cache.get(norm_name, [])
                for cached_norm_addr, cached_id in candidates:
                    if norm_address in cached_norm_addr or cached_norm_addr in norm_address:
                        shop_id = cached_id
                        break

            if not shop_id:
                shop_record = Shop(
                    name=name,
                    prefecture=item['prefecture'],
                    address=address,
                    phone=item['phone'],
                    website_url=item['website_url'],
                    business_hours=item['business_hours'],
                    regular_holiday=item['regular_holiday'],
                    image_url=item['image_url']
                )
                self.session.add(shop_record)
                self.session.flush()
                shop_id = shop_record.id
                if norm_name not in self.shop_cache: self.shop_cache[norm_name] = []
                self.shop_cache[norm_name].append((norm_address, shop_id))
            else:
                shop_record = self.session.query(Shop).get(shop_id)
                shop_record.prefecture = item['prefecture']
                shop_record.business_hours = item['business_hours']
                shop_record.regular_holiday = item['regular_holiday']
                if item['image_url']: shop_record.image_url = item['image_url']
                shop_record.updated_at = datetime.datetime.now()

            if identifier and (self.site_id, identifier) not in self.ident_cache:
                self.session.add(ShopIdentifier(shop_id=shop_id, site_id=self.site_id, identifier=identifier))
                self.ident_cache[(self.site_id, identifier)] = shop_id
            
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
class BdsShopSpider(scrapy.Spider):
    name = "bds_shop_spider"
    allowed_domains = ["www.bds-bikesensor.net"]

    # 都道府県コードと名称のマップ
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
        'CONCURRENT_REQUESTS': 16,
        'DOWNLOAD_DELAY': 0.5,
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
            identifier = re.search(r'client/(\d+)', href).group(1) if href else None

            address, hours, holiday, phone = "", "", "", ""
            rows = unit.css(".c-search_block_shop-info_table table tr")
            for row in rows:
                th = row.css("th::text").get()
                td_text = row.css("td").xpath("string(.)").get().strip()
                if not th: continue
                if "住所" in th: address = td_text
                elif "営業時間" in th: hours = td_text
                elif "定休日" in th: holiday = td_text
                elif "電話番号" in th: phone = td_text

            img_url = unit.css("figure.c-delay_load::attr(data-src)").get()

            yield {
                'name': name,
                'prefecture': pref_name, # metaから取得
                'address': address,
                'phone': phone,
                'website_url': response.urljoin(href) if href else None,
                'identifier': identifier,
                'business_hours': hours,
                'regular_holiday': holiday,
                'image_url': response.urljoin(img_url) if img_url else None
            }

        next_page = response.css("div.c-pager a.c-btn_next::attr(href)").get()
        if next_page:
            yield response.follow(next_page, callback=self.parse_shop_list, meta={'prefecture': pref_name})

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(BdsShopSpider)
    process.start()