import scrapy
from scrapy.crawler import CrawlerProcess
import os
import re
import datetime
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, ForeignKey, UniqueConstraint
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# 1. 環境変数の読み込み
current_dir = os.path.dirname(os.path.abspath(__file__))
env_path = os.path.join(current_dir, '..', '..', '.env')
load_dotenv(dotenv_path=env_path)

if not os.getenv("DB_DATABASE"):
    load_dotenv()

# 2. データベース設定
user = os.getenv("DB_USERNAME", "sail")
password = os.getenv("DB_PASSWORD", "password")
host = os.getenv("DB_HOST", "db")
port = os.getenv("DB_PORT", "3306")
database = os.getenv("DB_DATABASE", "motohub")
DATABASE_URL = f"mysql+pymysql://{user}:{password}@{host}:{port}/{database}"

engine = create_engine(DATABASE_URL)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

class Base(DeclarativeBase):
    pass

# --- DBモデル定義 ---
class Site(Base):
    __tablename__ = "sites"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(50), unique=True)

class Manufacturer(Base):
    __tablename__ = "manufacturers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    name = Column(String(100), unique=True, nullable=False)

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    manufacturer_id = Column(BigInteger, nullable=False)
    name = Column(String(255), nullable=False, unique=True)
    category = Column(String(50), nullable=True)
    displacement = Column(Integer, nullable=True)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

class BikeModelIdentifier(Base):
    __tablename__ = "bike_model_identifiers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    bike_model_id = Column(BigInteger, ForeignKey("bike_models.id", ondelete="CASCADE"), nullable=False)
    site_id = Column(BigInteger, ForeignKey("sites.id", ondelete="CASCADE"), nullable=False)
    identifier = Column(String(100), nullable=False)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_site_identifier_uc'),)

# 3. Scrapy Spiderの定義
class BDSModelSpider(scrapy.Spider):
    name = "bds_models"
    allowed_domains = ["www.bds-bikesensor.net"]
    
    # 巡回するメーカーリスト
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
        super(BDSModelSpider, self).__init__(*args, **kwargs)
        self.db = SessionLocal()
        
        # サイトIDの取得
        site = self.db.query(Site).filter(Site.name == "BDS").first()
        self.site_id = site.id if site else None
        
        # キャッシュの構築
        self.manufacturer_cache = {m.name: m.id for m in self.db.query(Manufacturer).all()}
        self.existing_models = {m.name: m.id for m in self.db.query(BikeModel).all()}

    def closed(self, reason):
        self.db.close()

    def start_requests(self):
        base_url = "https://www.bds-bikesensor.net/bike/maker/"
        for maker in self.MAKER_LIST:
            # メーカー情報の取得・登録
            m_id = self.manufacturer_cache.get(maker['name'])
            if not m_id:
                m_record = Manufacturer(name=maker['name'])
                self.db.add(m_record)
                self.db.flush()
                m_id = m_record.id
                self.manufacturer_cache[maker['name']] = m_id
                self.db.commit()

            yield scrapy.Request(
                url=base_url + maker['slug'],
                callback=self.parse,
                meta={'maker_id': m_id, 'maker_name': maker['name']}
            )

    def parse(self, response):
        """メーカーの車種一覧ページからデータを抽出"""
        maker_id = response.meta['maker_id']
        maker_name = response.meta['maker_name']
        
        # 車種ブロックの取得
        model_blocks = response.css('.model_item')
        new_count = 0

        for block in model_blocks:
            # 識別番号の取得
            identifier_val = block.css('input.model-checkbox::attr(value)').get()
            # 車種名の取得
            raw_model_name = block.css('a.c-bike_image::attr(title)').get()
            
            if not raw_model_name or not identifier_val:
                continue

            # 名称のクレンジング
            model_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', raw_model_name).strip()
            
            # 車種マスタへの登録
            model_id = self.existing_models.get(model_name)
            if not model_id:
                # DBに再確認（並列実行時の重複回避）
                db_model = self.db.query(BikeModel).filter(BikeModel.name == model_name).first()
                if db_model:
                    model_id = db_model.id
                else:
                    new_model = BikeModel(
                        name=model_name,
                        manufacturer_id=maker_id,
                        category="不明"
                    )
                    self.db.add(new_model)
                    self.db.flush()
                    model_id = new_model.id
                    new_count += 1
                
                self.existing_models[model_name] = model_id

            # BDS固有の識別番号を紐付け
            if self.site_id and identifier_val:
                exists = self.db.query(BikeModelIdentifier).filter(
                    BikeModelIdentifier.site_id == self.site_id,
                    BikeModelIdentifier.identifier == identifier_val
                ).first()
                
                if not exists:
                    self.db.add(BikeModelIdentifier(
                        bike_model_id=model_id,
                        site_id=self.site_id,
                        identifier=identifier_val
                    ))
        
        self.db.commit()
        if new_count > 0:
            self.logger.info(f"Registered {new_count} new models for {maker_name}")

# 実行用メイン関数
def main():
    print("BDSモデルコレクター (Scrapy版) を実行中...")
    process = CrawlerProcess()
    process.crawl(BDSModelSpider)
    process.start()
    print("収集が完了しました。")

if __name__ == "__main__":
    main()