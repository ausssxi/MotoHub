import sys
import os

# ==========================================
# 0. インポートパスの解決（相対インポートエラー対策）
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if parent_dir not in sys.path:
    sys.path.append(parent_dir)

import scrapy
from scrapy.crawler import CrawlerProcess
import re
import datetime
import logging
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, ForeignKey, UniqueConstraint
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import DeclarativeBase, sessionmaker
from dotenv import load_dotenv

# 共通ユーティリティをインポート
from utils import normalize_name, extract_displacement, model_match_key
from common.base_spider import MOTOHUB_USER_AGENT
# 共通基盤（必要に応じて common からインポートする形式に合わせることも可能ですが、現状の定義を維持します）
# from common.database import Site, Manufacturer, BikeModel, BikeModelIdentifier

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

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    manufacturer_id = Column(BigInteger, nullable=False)
    name = Column(String(255), nullable=False, unique=True)
    category = Column(String(50), nullable=True)
    displacement = Column(Integer, nullable=True)
    image_url = Column(String(255), nullable=True)
    # 照合で canonical のみを対象にするため参照（統合済みの重複行を再利用しない）。
    merged_into_id = Column(BigInteger, nullable=True)

class BikeModelIdentifier(Base):
    __tablename__ = "bike_model_identifiers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    bike_model_id = Column(BigInteger, ForeignKey("bike_models.id", ondelete="CASCADE"), nullable=False)
    site_id = Column(BigInteger, ForeignKey("sites.id", onupdate="CASCADE", ondelete="CASCADE"), nullable=False)
    identifier = Column(String(100), nullable=False)
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_site_identifier_uc'),)

# ==========================================
# 2. Scrapy Spider (モデルコレクター)
# ==========================================
class ModelSpider(scrapy.Spider):
    name = "model_collector"
    allowed_domains = ["www.goobike.com"]
    start_urls = ["https://www.goobike.com/maker-top/index.html"]

    # ✨ 修正：パイプラインと中断設定を追加
    custom_settings = {
        'REQUEST_FINGERPRINTER_IMPLEMENTATION': '2.7',
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 1.0,
        'COOKIES_ENABLED': False,
        'USER_AGENT': MOTOHUB_USER_AGENT,
        'ROBOTSTXT_OBEY': True,
        'ITEM_PIPELINES': {
            'common.pipelines.MotoHubImagePipeline': 300,
        },
    }

    def __init__(self, *args, **kwargs):
        super(ModelSpider, self).__init__(*args, **kwargs)
        self.db = SessionLocal()

        target_site_name = "GooBike"
        site = self.db.query(Site).filter(Site.name == target_site_name).first()
        if not site:
            try:
                site = Site(name=target_site_name, base_url="https://www.goobike.com")
                self.db.add(site)
                self.db.commit()
            except IntegrityError:
                self.db.rollback()
                site = self.db.query(Site).filter(Site.name == target_site_name).first()
        
        self.site_id = site.id
        self.manufacturer_cache = {m.name: m.id for m in self.db.query(Manufacturer).all()}

    def parse(self, response):
        maker_links = response.css('span.mj a')
        for link in maker_links:
            # 💡 中断信号を受けている場合はリクエストを停止
            if not self.crawler.engine.running:
                return

            raw_name = link.css('::text').get()
            href = link.css('::attr(href)').get()
            if not raw_name or not href:
                continue

            maker_name = normalize_name(re.sub(r'[\(\uff08].*?[\)\uff09]', '', raw_name))
            
            m_id = self.manufacturer_cache.get(maker_name)
            if not m_id:
                try:
                    m_record = Manufacturer(name=maker_name, country="不明")
                    self.db.add(m_record)
                    self.db.commit()
                    m_id = m_record.id
                except IntegrityError:
                    self.db.rollback()
                    m_record = self.db.query(Manufacturer).filter(Manufacturer.name == maker_name).first()
                    m_id = m_record.id if m_record else None
                
                if m_id:
                    self.manufacturer_cache[maker_name] = m_id

            if m_id:
                yield response.follow(
                    href, 
                    callback=self.parse_models, 
                    meta={'maker_id': m_id, 'maker_name': maker_name}
                )

    def parse_models(self, response):
        # 💡 中断信号を受けている場合は処理をスキップ
        if not self.crawler.engine.running:
            return

        maker_id = response.meta['maker_id']
        bike_list = response.css('li.bike_list')
        
        for bike in bike_list:
            # 💡 ループ内でも停止状態をチェック
            if not self.crawler.engine.running:
                break

            raw_model_name = bike.css('em b::text').get()
            identifier_val = bike.css('input[name="model"]::attr(value)').get()
            
            img_rel_url = bike.css('img.lazy::attr(data-original)').get() or bike.css('img::attr(src)').get()
            image_url = response.urljoin(img_rel_url) if img_rel_url else None
            
            if image_url and 'img_noimages.jpg' in image_url:
                image_url = None

            if not raw_model_name:
                continue

            clean_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', raw_model_name)
            model_name = normalize_name(clean_name)
            inferred_displacement = extract_displacement(raw_model_name)
            
            try:
                model_id = None
                # 1) 正規化名で完全一致（indexあり・高速）。
                model_record = self.db.query(BikeModel).filter(BikeModel.name == model_name).first()
                # 2) 外れたら表記ゆれ（ダッシュ/中黒/区切り違い）対策として、メーカー内の
                #    canonical 車種を照合キーで走査し、一致すれば再利用（重複作成を防ぐ）。
                if not model_record:
                    match_key = model_match_key(model_name)
                    for cand in self.db.query(BikeModel).filter(
                        BikeModel.manufacturer_id == maker_id,
                        BikeModel.merged_into_id.is_(None),
                    ).all():
                        if model_match_key(cand.name) == match_key:
                            model_record = cand
                            # 2段目（キー照合）での再利用は、別車種を誤って統合する可能性があるため必ずログに残す。
                            self.logger.info(
                                f"[dedup] キー照合で既存車種を再利用: maker_id={maker_id} "
                                f"入力='{model_name}' -> 既存 id={cand.id} name='{cand.name}' key='{match_key}'"
                            )
                            break

                if not model_record:
                    new_model = BikeModel(
                        name=model_name, 
                        manufacturer_id=maker_id, 
                        category="不明",
                        displacement=inferred_displacement,
                        image_url=image_url
                    )
                    self.db.add(new_model)
                    self.db.commit()
                    model_id = new_model.id
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
            
            except IntegrityError:
                self.db.rollback()
                continue
            except Exception as e:
                self.db.rollback()
                self.logger.error(f"Error in parse_models: {e}")

    def closed(self, reason):
        # モデルコレクターは listings のような重い完売チェックはありませんが、
        # 中断された場合はその旨をログに残します。
        if reason != 'finished':
            self.logger.info(f"Spider closed by {reason}. Stopping model collection.")
        self.db.close()

def main():
    process = CrawlerProcess()
    process.crawl(ModelSpider)
    process.start()

if __name__ == "__main__":
    main()