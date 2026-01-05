import scrapy
from scrapy.crawler import CrawlerProcess
import os
import re
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, ForeignKey, UniqueConstraint
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# 環境変数の読み込み
load_dotenv()
if not os.getenv("DB_DATABASE"):
    load_dotenv(dotenv_path='../../.env')

# データベース設定
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
    country = Column(String(50), nullable=True)

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    manufacturer_id = Column(BigInteger, nullable=False)
    name = Column(String(255), nullable=False, unique=True)
    category = Column(String(50), nullable=True)
    displacement = Column(Integer, nullable=True)

class BikeModelIdentifier(Base):
    __tablename__ = "bike_model_identifiers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    bike_model_id = Column(BigInteger, ForeignKey("bike_models.id", ondelete="CASCADE"), nullable=False)
    site_id = Column(BigInteger, ForeignKey("sites.id", onupdate="CASCADE", ondelete="CASCADE"), nullable=False)
    identifier = Column(String(100), nullable=False)
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_site_identifier_uc'),)

# --- Scrapy Spider ---
class GooBikeModelSpider(scrapy.Spider):
    name = "goobike_models"
    allowed_domains = ["www.goobike.com"]
    start_urls = ["https://www.goobike.com/maker-top/index.html"]

    custom_settings = {
        'REQUEST_FINGERPRINTER_IMPLEMENTATION': '2.7', # 警告の解消
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 1.0,
        'COOKIES_ENABLED': False,
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    }

    def __init__(self, *args, **kwargs):
        super(GooBikeModelSpider, self).__init__(*args, **kwargs)
        self.db = SessionLocal()
        site = self.db.query(Site).filter(Site.name == "GooBike").first()
        self.site_id = site.id if site else None
        self.manufacturer_cache = {m.name: m.id for m in self.db.query(Manufacturer).all()}
        self.existing_models = {m.name: m.id for m in self.db.query(BikeModel).all()}

    def closed(self, reason):
        self.db.close()

    def parse(self, response):
        """メーカー一覧ページから各メーカーURLを取得"""
        # セレクターをより汎用的なものに変更
        # .mj a はメーカーリンクに直接ヒットするはずです
        maker_links = response.css('span.mj a')
        
        if not maker_links:
            self.logger.warning("メーカーリンクが見つかりませんでした。セレクターを確認してください。")
            return

        for link in maker_links:
            raw_name = link.css('::text').get()
            href = link.css('::attr(href)').get()
            
            if not raw_name or not href:
                continue

            maker_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', raw_name).strip()
            
            # メーカー登録（必要に応じて）
            m_id = self.manufacturer_cache.get(maker_name)
            if not m_id:
                m_record = Manufacturer(name=maker_name, country="不明")
                self.db.add(m_record)
                self.db.flush()
                m_id = m_record.id
                self.manufacturer_cache[maker_name] = m_id
                self.db.commit()

            yield response.follow(
                href, 
                callback=self.parse_models, 
                meta={'maker_id': m_id, 'maker_name': maker_name}
            )

    def parse_models(self, response):
        """車種一覧ページからデータを抽出"""
        maker_id = response.meta['maker_id']
        maker_name = response.meta['maker_name']
        bike_list = response.css('li.bike_list')
        
        new_count = 0
        for bike in bike_list:
            raw_model_name = bike.css('em b::text').get()
            identifier_val = bike.css('input[name="model"]::attr(value)').get()
            
            if not raw_model_name:
                continue

            model_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', raw_model_name).strip()
            
            # 車種登録
            model_id = self.existing_models.get(model_name)
            if not model_id:
                new_model = BikeModel(name=model_name, manufacturer_id=maker_id, category="不明")
                self.db.add(new_model)
                self.db.flush()
                model_id = new_model.id
                self.existing_models[model_name] = model_id
                new_count += 1

            if self.site_id and identifier_val:
                exists = self.db.query(BikeModelIdentifier).filter(
                    BikeModelIdentifier.site_id == self.site_id,
                    BikeModelIdentifier.identifier == identifier_val
                ).first()
                if not exists:
                    self.db.add(BikeModelIdentifier(bike_model_id=model_id, site_id=self.site_id, identifier=identifier_val))
        
        self.db.commit()
        if new_count > 0:
            self.logger.info(f"Registered {new_count} new models for {maker_name}")

def main():
    process = CrawlerProcess()
    process.crawl(GooBikeModelSpider)
    process.start()

if __name__ == "__main__":
    main()