import scrapy
from scrapy.crawler import CrawlerProcess
from scrapy.signalmanager import dispatcher
from scrapy import signals
import os
import re
import datetime
import sys
import random
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
class BDSListingSpider(scrapy.Spider):
    name = "bds_listings"
    allowed_domains = ["www.bds-bikesensor.net"]
    
    # 巡回メーカーリスト
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

    custom_settings = {
        'REQUEST_FINGERPRINTER_IMPLEMENTATION': '2.7',
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 0.5,
        'COOKIES_ENABLED': False,
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    }

    def __init__(self, *args, **kwargs):
        super(BDSListingSpider, self).__init__(*args, **kwargs)
        self.db = SessionLocal()
        
        # サイトIDの取得
        site = self.db.query(Site).filter(Site.name == "BDS").first()
        self.site_id = site.id if site else None

        # キャッシュの構築
        self.model_ident_cache = {i.identifier: i.bike_model_id for i in self.db.query(BikeModelIdentifier).filter(BikeModelIdentifier.site_id == self.site_id).all()}
        self.shop_cache = {i.identifier: i.shop_id for i in self.db.query(ShopIdentifier).filter(ShopIdentifier.site_id == self.site_id).all()}
        
        # DBにある「販売中」のURLをロード
        self.known_urls = {l.source_url for l in self.db.query(Listing.source_url).filter(Listing.site_id == self.site_id, Listing.is_sold_out == False).all()}
        self.found_urls = set()

        # 終了時処理
        dispatcher.connect(self.spider_closed, signals.spider_closed)

    def start_requests(self):
        base_url = "https://www.bds-bikesensor.net/bike/maker/"
        for maker in self.MAKER_LIST:
            yield scrapy.Request(url=base_url + maker['slug'], callback=self.parse_maker_page)

    def parse_maker_page(self, response):
        """メーカーページから各車種の在庫一覧URLを取得"""
        model_items = response.css(".model_item")
        for item in model_items:
            m_input = item.css("input.model-checkbox::attr(value)").get()
            href = item.css("a.c-bike_image::attr(href)").get()
            
            if m_input and href:
                bike_model_id = self.model_ident_cache.get(m_input)
                if bike_model_id:
                    yield response.follow(
                        href, 
                        callback=self.parse_listings, 
                        meta={'bike_model_id': bike_model_id}
                    )

    def parse_listings(self, response):
        """出品一覧ページから車両データを抽出"""
        bike_model_id = response.meta['bike_model_id']
        bike_blocks = response.css("li.type_bike, li.type_bike_sp")
        
        for bike in bike_blocks:
            try:
                # URLとタイトル
                title_el = bike.css(".c-search_block_title a, .c-search_block_title02 a")
                if not title_el: continue
                
                v_url = response.urljoin(title_el.css("::attr(href)").get())
                v_title = title_el.css("::text").get().strip()

                # 今回見つかったURLとして記録
                self.found_urls.add(v_url)

                # 重複スキップ
                if v_url in self.known_urls:
                    continue

                # 価格取得
                price_val, total_price_val = 0, None
                price_items = bike.css(".c-search_block_price")
                for p_item in price_items:
                    l_text = p_item.css(".c-search_block_price_title::text").get()
                    v_text = "".join(p_item.css(".c-search_block_price_text *::text").getall()).replace(',', '').replace('\n', '').strip()
                    
                    match = re.search(r'(\d+\.?\d*)', v_text)
                    if match:
                        num = int(float(match.group(1)) * 10000)
                        if l_text and "本体価格" in l_text: price_val = num
                        elif l_text and "支払総額" in l_text: total_price_val = num

                # スペック取得 (年式・距離)
                year, mile = None, None
                status_cols = bike.css(".c-search_status_col")
                for col in status_cols:
                    h_txt = col.css(".c-search_status_head::text").get()
                    v_txt = "".join(col.css(".c-search_status_title01 *::text").getall())
                    
                    if h_txt and "モデル年" in h_txt and "不明" not in v_txt:
                        y_m = re.search(r'(\d{4})', v_txt)
                        if y_m: year = int(y_m.group(1))
                    elif h_txt and "距離" in h_txt:
                        m_m = re.search(r'(\d+)', v_txt.replace(',', ''))
                        if m_m: mile = int(m_m.group(1))

                # 画像
                img_url = bike.css(".c-bike_image figure.c-img_cover::attr(data-src)").get() or \
                          bike.css(".c-bike_image figure.c-img_cover::attr(src)").get()
                images = [response.urljoin(img_url)] if img_url and "blank" not in img_url else []

                # 販売店特定
                shop_id = None
                shop_href = bike.css(".c-search_block_bottom_lead a::attr(href)").get()
                if shop_href:
                    id_match = re.search(r'client/(\d+)', shop_href)
                    if id_match:
                        shop_id = self.shop_cache.get(id_match.group(1))

                # 保存
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
                self.logger.error(f"車両解析エラー: {e}")

        # ページネーション (もし存在すれば)
        next_page = response.css("div.c-pager a.c-btn_next::attr(href)").get()
        if next_page:
            yield response.follow(next_page, callback=self.parse_listings, meta=response.meta)

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
    print("BDS出品情報コレクター (Scrapy版) を起動しています...")
    process = CrawlerProcess()
    process.crawl(BDSListingSpider)
    process.start()
    print("すべての同期処理が完了しました。")

if __name__ == "__main__":
    main()