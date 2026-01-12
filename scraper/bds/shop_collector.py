import os
import re
import sys
import datetime
import logging
import scrapy
import unicodedata
from scrapy.crawler import CrawlerProcess
from sqlalchemy import create_engine, Column, BigInteger, String, Text, DateTime, ForeignKey, UniqueConstraint, Numeric, or_
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

# --- 正規化関数群 ---

def normalize_general(text: str) -> str:
    """共通の正規化（全角半角統一、スペース除去、小文字化）"""
    if not text: return ""
    text = unicodedata.normalize('NFKC', text)
    text = re.sub(r'\s+', '', text)
    return text.lower()

def normalize_shop_name(name: str) -> str:
    """
    店名の名寄せ用正規化
    括弧の除去、記号の除去、法人格の除去を行い、純粋な店名のみを抽出する
    """
    name = normalize_general(name)

    # 1. 括弧とその中身を削除 (例: (有)三田商会 -> 三田商会 / ミタモータース(福岡店) -> ミタモータース)
    # ただし、今回のように「(有)」が店名の一部として使われている場合は 2 の処理で対応
    name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', name)

    # 2. バイク業界で頻出するカタカナ語を英語に変換
    trans_map = {
        "アウトレット": "outlet", "モーター": "motor", "サイクル": "cycle",
        "ショップ": "shop", "センター": "center", "ファクトリー": "factory",
        "ガレージ": "garage", "ワークス": "works", "サービス": "service",
        "モータース": "motors", "カスタム": "custom",
    }
    for kana, eng in trans_map.items():
        name = name.replace(kana, eng)

    # 3. 記号（ハイフン、ドットなど）をすべて除去
    name = re.sub(r'[^a-z0-9\u4e00-\u9faf\u3040-\u309f\u30a0-\u30ff]', '', name)
    
    # 4. 法人格や不要なキーワードを除去
    noise = ["株式会社", "有限会社", "合資会社", "合同会社", "株", "有", "店", "販売店"]
    for word in noise:
        name = name.replace(word, "")
    
    return name

def normalize_address(addr: str) -> str:
    """住所の名寄せ用正規化"""
    addr = normalize_general(addr)
    addr = re.sub(r'[丁目|番地|番|号]', '-', addr)
    addr = re.sub(r'[－ー−―‐－-]', '-', addr)
    addr = re.sub(r'-+', '-', addr)
    return addr.strip('-')

def normalize_phone(phone: str) -> str:
    """電話番号の数字のみを抽出"""
    if not phone: return ""
    return re.sub(r'\D', '', phone)

class Base(DeclarativeBase): pass

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
# 2. Scrapy Pipeline (名寄せロジック強化版)
# ==========================================
class DatabasePipeline:
    def open_spider(self, spider):
        self.engine = create_engine(DATABASE_URL)
        self.Session = sessionmaker(bind=self.engine)
        self.session = self.Session()
        
        site = self.session.query(Site).filter(Site.name == "BDS").first()
        if not site:
            raise Exception("BDS site not found in DB.")
        self.site_id = site.id

        # 1. キャッシュの構築
        self.phone_cache = {}    
        self.name_addr_cache = {} # 正規化店名 -> [(正規化住所, shop_id)]
        
        shops = self.session.query(Shop).all()
        for s in shops:
            if s.phone:
                p_norm = normalize_phone(s.phone)
                if p_norm: self.phone_cache[p_norm] = s.id
            
            n_norm = normalize_shop_name(s.name)
            a_norm = normalize_address(s.address)
            if n_norm not in self.name_addr_cache:
                self.name_addr_cache[n_norm] = []
            self.name_addr_cache[n_norm].append((a_norm, s.id))
        
        idents = self.session.query(ShopIdentifier).all()
        self.ident_cache = {(si.site_id, si.identifier): si.shop_id for si in idents}

    def process_item(self, item, spider):
        try:
            name = item['name']
            address = item['address'] or ''
            phone = item['phone']
            identifier = item['identifier']

            # 1. 識別子でチェック
            shop_id = self.ident_cache.get((self.site_id, identifier))

            if not shop_id:
                # 2. 電話番号でチェック (最強)
                if phone:
                    p_norm = normalize_phone(phone)
                    shop_id = self.phone_cache.get(p_norm)

                # 3. 店名(部分一致可) + 住所(包含関係可) でチェック
                if not shop_id:
                    n_norm = normalize_shop_name(name)
                    a_norm = normalize_address(address)
                    
                    # キャッシュされている全ての正規化店名をループ
                    for cached_n_norm, addr_list in self.name_addr_cache.items():
                        # 店名がどちらかの包含関係にあるかチェック
                        # これにより「ミタモータース三田商会」と「ミタモータース」がマッチします
                        if n_norm and cached_n_norm and (n_norm in cached_n_norm or cached_n_norm in n_norm):
                            for cached_a_norm, cached_id in addr_list:
                                # 店名が怪しい場合も住所が一致（または包含）していれば同一店舗とみなす
                                if a_norm in cached_a_norm or cached_a_norm in a_norm:
                                    shop_id = cached_id
                                    break
                        if shop_id: break

            if not shop_id:
                # 新規登録
                shop_record = Shop(
                    name=name,
                    prefecture=item['prefecture'],
                    address=address,
                    phone=phone,
                    website_url=item['website_url'],
                    business_hours=item['business_hours'],
                    regular_holiday=item['regular_holiday'],
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
                spider.logger.info(f"New shop registered: {name}")
            else:
                # 更新
                shop_record = self.session.query(Shop).get(shop_id)
                if not shop_record.phone and phone: shop_record.phone = phone
                shop_record.business_hours = item['business_hours']
                shop_record.regular_holiday = item['regular_holiday']
                if item['image_url']: shop_record.image_url = item['image_url']
                shop_record.updated_at = datetime.datetime.now()

            # 識別子の紐付け
            if identifier and (self.site_id, identifier) not in self.ident_cache:
                self.session.add(ShopIdentifier(shop_id=shop_id, site_id=self.site_id, identifier=identifier))
                self.ident_cache[(self.site_id, identifier)] = shop_id
            
            self.session.commit()
        except Exception as e:
            self.session.rollback()
            spider.logger.error(f"Error saving shop {item['name']}: {e}")
        return item

    def close_spider(self, spider):
        self.session.close()

# ==========================================
# 3. Scrapy Spider
# ==========================================
class BdsShopSpider(scrapy.Spider):
    name = "bds_shop_spider"
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
                th_el = row.css("th")
                td_el = row.css("td")
                if not th_el or not td_el: continue
                
                th_txt = th_el.xpath("string(.)").get()
                td_txt = td_el.xpath("string(.)").get().strip()
                
                if "住所" in th_txt: address = td_txt
                elif "営業時間" in th_txt: hours = td_txt
                elif "定休日" in th_txt: holiday = td_txt
                elif "電話番号" in th_txt: phone = td_txt

            img_url = unit.css("figure.c-delay_load::attr(data-src)").get()

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

        next_page = response.css("div.c-pager a.c-btn_next::attr(href)").get()
        if next_page:
            yield response.follow(next_page, callback=self.parse_shop_list, meta={'prefecture': pref_name})

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(BdsShopSpider)
    process.start()