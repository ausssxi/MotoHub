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
from sqlalchemy import create_engine, Column, BigInteger, String, Numeric, Integer, Boolean, Text, JSON, DateTime, update
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# ==========================================
# 1. 環境設定 & データベース定義
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

DATABASE_URL = f"mysql+pymysql://{get_env_or_exit('DB_USERNAME')}:{get_env_or_exit('DB_PASSWORD')}@{get_env_or_exit('DB_HOST', 'db')}:{get_env_or_exit('DB_PORT', '3306')}/{get_env_or_exit('DB_DATABASE')}"

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
# 2. Database Pipeline (高速化の肝: Bulk Insert)
# ==========================================
class ListingPipeline:
    def __init__(self):
        self.engine = create_engine(DATABASE_URL, pool_size=10, max_overflow=20)
        self.Session = sessionmaker(bind=self.engine)
        self.items_buffer = []
        self.buffer_limit = 50 

    def open_spider(self, spider):
        self.session = self.Session()

    def process_item(self, item, spider):
        if item.get('is_new'):
            self.items_buffer.append(Listing(
                bike_model_id=item['bike_model_id'],
                shop_id=item['shop_id'],
                site_id=spider.site_id,
                title=item['title'],
                source_url=item['source_url'],
                price=item['price'],
                total_price=item['total_price'],
                model_year=item['model_year'],
                mileage=item['mileage'],
                first_registration=item.get('first_registration'),
                has_repair_history=item.get('has_repair_history', False),
                condition=item.get('condition', '中古車'),
                color=item.get('color'),
                image_urls=item['image_urls'],
                is_sold_out=False
            ))
            
            if len(self.items_buffer) >= self.buffer_limit:
                self.flush_buffer(spider)
        return item

    def flush_buffer(self, spider):
        if self.items_buffer:
            try:
                self.session.add_all(self.items_buffer)
                self.session.commit()
                spider.logger.info(f"DBに {len(self.items_buffer)} 件の車両を一括保存しました。")
                self.items_buffer = []
            except Exception as e:
                self.session.rollback()
                spider.logger.error(f"一括保存エラー: {e}")

    def close_spider(self, spider):
        self.flush_buffer(spider)
        self.session.close()

# ==========================================
# 3. Optimized Scrapy Spider
# ==========================================
class BDSListingSpider(scrapy.Spider):
    name = "bds_listings"
    allowed_domains = ["www.bds-bikesensor.net"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 32,
        'DOWNLOAD_DELAY': 0.1,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {'__main__.ListingPipeline': 300},
        'LOG_LEVEL': 'INFO',
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    }

    MAKER_LIST = [
        {"slug": "honda", "name": "ホンダ"}, {"slug": "suzuki", "name": "スズキ"},
        {"slug": "yamaha", "name": "ヤマハ"}, {"slug": "kawasaki", "name": "カワサキ"},
        {"slug": "daihatsu", "name": "ダイハツ"}, {"slug": "bridgestone", "name": "ブリジストン"},
        {"slug": "meguro", "name": "メグロ"}, {"slug": "rodeo", "name": "ロデオ"},
        {"slug": "plot", "name": "プロト"}, {"slug": "bmw", "name": "BMW"},
        {"slug": "ktm", "name": "KTM"}, {"slug": "aprilia", "name": "アプリリア"},
        {"slug": "mv_agusta", "name": "MVアグスタ"}, {"slug": "gilera", "name": "ジレラ"},
        {"slug": "ducati", "name": "ドゥカティ"}, {"slug": "triumph", "name": "トライアンフ"},
        {"slug": "norton", "name": "ノートン"}, {"slug": "harley_davidson", "name": "ハーレーダビッドソン"},
        {"slug": "husqvarna", "name": "ハスクバーナ"}, {"slug": "bimota", "name": "ビモータ"},
        {"slug": "buell", "name": "ビューエル"}, {"slug": "vespa", "name": "ベスパ"},
        {"slug": "moto_guzzi", "name": "モトグッツィ"}, {"slug": "royal_enfield", "name": "ロイヤルエンフィールド"},
        {"slug": "daelim", "name": "DAELIM"}, {"slug": "gg", "name": "GG"},
        {"slug": "pgo", "name": "PGO"}, {"slug": "sym", "name": "SYM"},
        {"slug": "italjet", "name": "イタルジェット"}, {"slug": "gasgas", "name": "ガスガス"},
        {"slug": "kymco", "name": "キムコ"}, {"slug": "krauser", "name": "クラウザー"},
        {"slug": "sachs", "name": "ザックス"}, {"slug": "derbi", "name": "デルビ"},
        {"slug": "tomos", "name": "トモス"}, {"slug": "piaggio", "name": "ピアジオ"},
        {"slug": "bsa", "name": "ビーエスエー"}, {"slug": "fantic", "name": "ファンティック"},
        {"slug": "peugeot", "name": "プジョー"}, {"slug": "beta", "name": "ベータ"},
        {"slug": "benelli", "name": "ベネリ"}, {"slug": "magni", "name": "マーニ"},
        {"slug": "moto_morini", "name": "モトモリーニ"}, {"slug": "mondial", "name": "モンディアル"},
        {"slug": "montesa", "name": "モンテッサ"}, {"slug": "lambretta", "name": "ランブレッタ"},
        {"slug": "adiva", "name": "アディバ"}, {"slug": "megelli", "name": "メガリ"},
        {"slug": "indian", "name": "インディアン"}, {"slug": "gpx", "name": "GPX"},
        {"slug": "phoenix", "name": "PHOENIX"}, {"slug": "leonart", "name": "レオンアート"},
        {"slug": "brp", "name": "BRP"}, {"slug": "brixton", "name": "BRIXTON"},
        {"slug": "mutt", "name": "MUTT"},
    ]

    def __init__(self, *args, **kwargs):
        super(BDSListingSpider, self).__init__(*args, **kwargs)
        self.engine = create_engine(DATABASE_URL)
        self.Session = sessionmaker(bind=self.engine)
        session = self.Session()
        
        # サイトIDの確認 (ここが0だと動きません)
        site = session.query(Site).filter(Site.name == "BDS").first()
        self.site_id = site.id if site else 0
        if self.site_id == 0:
            logging.error("致命的警告: sitesテーブルに 'BDS' が登録されていません。")

        self.model_ident_cache = {i.identifier: i.bike_model_id for i in session.query(BikeModelIdentifier).filter(BikeModelIdentifier.site_id == self.site_id).all()}
        self.shop_cache = {i.identifier: i.shop_id for i in session.query(ShopIdentifier).filter(ShopIdentifier.site_id == self.site_id).all()}
        self.known_urls = {l.source_url for l in session.query(Listing.source_url).filter(Listing.site_id == self.site_id, Listing.is_sold_out == False).all()}
        self.found_urls = set()
        
        logging.info(f"起動準備完了: 登録済みモデル識別子={len(self.model_ident_cache)}件, 既知のURL={len(self.known_urls)}件")
        session.close()

        dispatcher.connect(self.spider_closed, signals.spider_closed)

    def start_requests(self):
        if self.site_id == 0: return
        base_url = "https://www.bds-bikesensor.net/bike/maker/"
        for maker in self.MAKER_LIST:
            yield scrapy.Request(url=base_url + maker['slug'], callback=self.parse_maker_page)

    def parse_maker_page(self, response):
        # 構造変更に対応するため、より広いセレクタを使用
        model_items = response.css("div.col, .model_item")
        found_models = 0
        
        for item in model_items:
            m_input = item.css("input.model-checkbox::attr(value)").get()
            href = item.css("a.c-bike_image::attr(href), a.c-link_block::attr(href)").get()
            
            if m_input and href:
                bike_model_id = self.model_ident_cache.get(m_input)
                if bike_model_id:
                    found_models += 1
                    yield response.follow(href, callback=self.parse_listings, meta={'bike_model_id': bike_model_id})

        self.logger.info(f"メーカーページ {response.url}: {found_models}件の登録済み車種を検出しました。")

    def parse_listings(self, response):
        bike_model_id = response.meta['bike_model_id']
        bike_blocks = response.css("li.type_bike, li.type_bike_sp")
        
        for bike in bike_blocks:
            title_el = bike.css(".c-search_block_title a, .c-search_block_title02 a")
            if not title_el: continue
            
            v_url = response.urljoin(title_el.css("::attr(href)").get())

            # 修正ポイント: 重複チェックを「add」より先に、かつ found_urls も含めて行う
            if v_url in self.known_urls or v_url in self.found_urls:
                continue

            # 新しく見つかったURLとして記録
            self.found_urls.add(v_url)

            # 解析・保存処理へ...
            item_data = self.extract_bike_data(response, bike, bike_model_id, v_url)
            item_data['is_new'] = True
            yield item_data

    def extract_bike_data(self, response, bike, bike_model_id, v_url):
        price_val, total_price_val = 0, None
        for p_item in bike.css(".c-search_block_price"):
            l_text = p_item.css(".c-search_block_price_title::text").get()
            v_text = "".join(p_item.css(".c-search_block_price_text *::text").getall()).replace(',', '').strip()
            match = re.search(r'(\d+\.?\d*)', v_text)
            if match:
                num = int(float(match.group(1)) * 10000)
                if l_text and "本体価格" in l_text: price_val = num
                elif l_text and "支払総額" in l_text: total_price_val = num

        year, mile, first_reg, color = None, None, None, None
        has_repair = False
        for col in bike.css(".c-search_status_col"):
            h_txt = col.css(".c-search_status_head::text").get()
            v_txt = "".join(col.css(".c-search_status_title01 *::text").getall()).strip()
            if not h_txt: continue

            if "モデル年" in h_txt:
                y_m = re.search(r'(\d{4})', v_txt)
                if y_m: year = int(y_m.group(1))
            elif "距離" in h_txt:
                m_m = re.search(r'(\d+)', v_txt.replace(',', ''))
                if m_m: mile = int(m_m.group(1))
            elif "初度登録" in h_txt:
                first_reg = v_txt
            elif "修復歴" in h_txt:
                has_repair = True if "あり" in v_txt else False
            elif "色" in h_txt:
                color = v_txt

        img_url = bike.css("figure.c-img_cover::attr(data-src)").get() or bike.css("figure.c-img_cover::attr(src)").get()
        shop_href = bike.css(".c-search_block_bottom_lead a::attr(href), .c-search_block_shop_title01 a::attr(href)").get()
        shop_id = None
        if shop_href:
            id_match = re.search(r'client/(\d+)', shop_href)
            if id_match: shop_id = self.shop_cache.get(id_match.group(1))

        return {
            'bike_model_id': bike_model_id,
            'shop_id': shop_id,
            'title': (bike.css(".c-search_block_title a::text, .c-search_block_title02 a::text").get() or "").strip(),
            'source_url': v_url,
            'price': price_val,
            'total_price': total_price_val,
            'model_year': year,
            'mileage': mile,
            'first_registration': first_reg,
            'has_repair_history': has_repair,
            'color': color,
            'condition': '中古車',
            'image_urls': [response.urljoin(img_url)] if img_url else []
        }

    def spider_closed(self, spider):
        session = self.Session()
        missing_urls = self.known_urls - self.found_urls
        if missing_urls:
            missing_list = list(missing_urls)
            for i in range(0, len(missing_list), 100):
                chunk = missing_list[i:i + 100]
                session.execute(update(Listing).where(Listing.source_url.in_(chunk)).where(Listing.site_id == self.site_id).values(is_sold_out=True, updated_at=datetime.datetime.now()))
            session.commit()
            logging.info(f"掲載終了判定: {len(missing_urls)} 件を売約済みとして更新しました。")
        session.close()

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(BDSListingSpider)
    process.start()