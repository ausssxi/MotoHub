import sys
import os

# ==========================================
# 0. インポートパスの解決（相対インポートエラー対策）
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir) # scraper フォルダ
if parent_dir not in sys.path:
    sys.path.append(parent_dir)

import scrapy
from scrapy.crawler import CrawlerProcess
import re
import logging
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, ForeignKey, UniqueConstraint
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import DeclarativeBase, sessionmaker
from dotenv import load_dotenv

# 共通ユーティリティをインポート
from utils import normalize_name, extract_displacement
from common.base_spider import MOTOHUB_USER_AGENT

# ==========================================
# 1. 環境設定 & DB接続
# ==========================================
env_path = os.path.join(parent_dir, '..', '.env')
load_dotenv(dotenv_path=env_path)

def get_env_or_exit(key):
    val = os.getenv(key)
    if val is None:
        logging.error(f"致命的エラー: 必須の環境変数 '{key}' が設定されていません。")
        sys.exit(1)
    return val

DB_USER = get_env_or_exit("DB_USERNAME")
DB_PASS = get_env_or_exit("DB_PASSWORD")
DB_HOST = get_env_or_exit("DB_HOST")
DB_PORT = get_env_or_exit("DB_PORT")
DB_NAME = get_env_or_exit("DB_DATABASE")

DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}"

engine = create_engine(DATABASE_URL, pool_pre_ping=True)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

class Base(DeclarativeBase):
    pass

class Site(Base):
    __tablename__ = "sites"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(50), unique=True)
    base_url = Column(String(255), nullable=True)

class Manufacturer(Base):
    __tablename__ = "manufacturers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    name = Column(String(100), unique=True, nullable=False)
    country = Column(String(50), nullable=True)
    logo_url = Column(String(255), nullable=True)

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    manufacturer_id = Column(BigInteger, nullable=False)
    name = Column(String(255), nullable=False, unique=True)
    category = Column(String(50), nullable=True)
    displacement = Column(Integer, nullable=True)
    image_url = Column(String(255), nullable=True)

class BikeModelIdentifier(Base):
    __tablename__ = "bike_model_identifiers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    bike_model_id = Column(BigInteger, ForeignKey("bike_models.id", ondelete="CASCADE"), nullable=False)
    site_id = Column(BigInteger, ForeignKey("sites.id", onupdate="CASCADE", ondelete="CASCADE"), nullable=False)
    identifier = Column(String(100), nullable=False)
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_site_identifier_uc'),)

# ==========================================
# 2. Scrapy Spider (Webike モデルコレクター)
# ==========================================
class WebikeModelSpider(scrapy.Spider):
    name = "webike_model_collector"
    allowed_domains = ["moto.webike.net", "img.webike-cdn.net"]
    start_urls = ["https://moto.webike.net/maker/"]

    # ✨ 修正：パイプラインと中断設定を追加
    custom_settings = {
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 1.2,
        'COOKIES_ENABLED': False,
        'USER_AGENT': MOTOHUB_USER_AGENT,
        'ROBOTSTXT_OBEY': True,
        'ITEM_PIPELINES': {
            'common.pipelines.MotoHubImagePipeline': 300,
        },
    }

    def __init__(self, *args, **kwargs):
        super(WebikeModelSpider, self).__init__(*args, **kwargs)
        self.db = SessionLocal()
        
        target_site_name = "Webike"
        site = self.db.query(Site).filter(Site.name == target_site_name).first()
        if not site:
            try:
                site = Site(name=target_site_name, base_url="https://moto.webike.net")
                self.db.add(site)
                self.db.commit()
            except IntegrityError:
                self.db.rollback()
                site = self.db.query(Site).filter(Site.name == target_site_name).first()
        
        self.site_id = site.id
        self.manufacturer_cache = {m.name: m.id for m in self.db.query(Manufacturer).all()}

    def parse(self, response):
        """1. メーカー一覧ページから情報を取得"""
        sections = response.xpath('//div[contains(@class, "makersearchbox")]//div[contains(@class, "top") or contains(@class, "maker")]')
        current_country = "不明"
        
        for section in sections:
            # 💡 中断信号を受けている場合は停止
            if not self.crawler.engine.running:
                return

            classes = section.root.attrib.get('class', '')
            if 'top' in classes:
                current_country = section.css('span::text').get(default="不明").strip()
                continue
            
            if 'maker' in classes:
                for li in section.css('ul.dotline li'):
                    if not self.crawler.engine.running: break

                    link = li.css('a')
                    if link:
                        raw_name = link.xpath('string(.)').get()
                        href = link.css('::attr(href)').get()
                    else:
                        raw_name = li.css('span.no_bike::text').get()
                        href = None

                    if not raw_name: continue

                    clean_name = re.sub(r'\s*[\(\uff08].*?[\)\uff09]', '', raw_name).strip()
                    maker_name = normalize_name(clean_name)

                    m_id = self.manufacturer_cache.get(maker_name)
                    if not m_id:
                        try:
                            m_record = Manufacturer(name=maker_name, country=current_country)
                            self.db.add(m_record)
                            self.db.commit()
                            m_id = m_record.id
                            self.manufacturer_cache[maker_name] = m_id
                        except IntegrityError:
                            self.db.rollback()
                            m_id = self.db.query(Manufacturer).filter(Manufacturer.name == maker_name).first().id

                    if m_id and href:
                        yield response.follow(href, callback=self.parse_models, meta={'maker_id': m_id})

    def parse_models(self, response):
        """2. メーカー別車種一覧ページからデータを抽出"""
        # 💡 中断信号を受けている場合は停止
        if not self.crawler.engine.running:
            return

        maker_id = response.meta['maker_id']
        bike_items = response.css('div.motoset ul.dotline li')
        
        scraped_count = 0
        for item in bike_items:
            # 💡 ループ内でも停止状態をチェック
            if not self.crawler.engine.running:
                break

            raw_model_name = item.css('p.model_name a::text').get()
            identifier_val = item.css('input[name="model_code_checkList"]::attr(value)').get()
            
            img_url = item.css('img::attr(data-src)').get() or item.css('img::attr(src)').get()
            image_url = response.urljoin(img_url) if img_url else None
            
            if image_url and ('moto_no_image' in image_url or 'sys_images/bg.png' in image_url):
                image_url = None

            if not raw_model_name or not identifier_val:
                continue

            model_name = normalize_name(raw_model_name)
            inferred_displacement = extract_displacement(raw_model_name)

            try:
                model_id = None
                model_record = self.db.query(BikeModel).filter(BikeModel.name == model_name).first()
                if not model_record:
                    model_record = BikeModel(
                        name=model_name, 
                        manufacturer_id=maker_id, 
                        image_url=image_url,
                        displacement=inferred_displacement,
                        category="不明"
                    )
                    self.db.add(model_record)
                    self.db.commit()
                    model_id = model_record.id
                else:
                    model_id = model_record.id
                    needs_update = False
                    if image_url and not model_record.image_url:
                        model_record.image_url = image_url
                        needs_update = True
                    if inferred_displacement and not model_record.displacement:
                        model_record.displacement = inferred_displacement
                        needs_update = True
                    
                    if needs_update:
                        self.db.commit()

                # 識別子の紐付け
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
                
                # ✨ 修正：共通パイプラインにアイテムを渡す（カタログ画像保存用）
                if model_id and image_url:
                    yield {
                        'target_type': 'model',
                        'id': model_id,
                        'image_urls': [image_url]
                    }
                
                scraped_count += 1

            except IntegrityError:
                self.db.rollback()
                continue
            except Exception as e:
                self.db.rollback()
                self.logger.error(f"Error processing {model_name}: {e}")

        self.logger.info(f"Finished parsing models for maker_id {maker_id}. Found {scraped_count} models.")

    def closed(self, reason):
        if reason != 'finished':
            self.logger.info(f"Spider closed by user ({reason}). Stopping collection.")
        self.db.close()

def main():
    process = CrawlerProcess()
    process.crawl(WebikeModelSpider)
    process.start()

if __name__ == "__main__":
    main()