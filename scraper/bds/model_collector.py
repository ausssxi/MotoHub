import sys
import os

# ==========================================
# 0. インポートパスの解決
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if parent_dir not in sys.path:
    sys.path.append(parent_dir)

import scrapy
from scrapy.crawler import CrawlerProcess
import re
import logging
import datetime
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, ForeignKey, UniqueConstraint
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import DeclarativeBase, sessionmaker
from dotenv import load_dotenv

# 共通ユーティリティをインポート
from utils import normalize_name, extract_displacement, model_match_key
from common.base_spider import MOTOHUB_USER_AGENT

# ==========================================
# 1. 環境設定
# ==========================================
env_path = os.path.join(parent_dir, '..', '.env')
load_dotenv(dotenv_path=env_path)

def get_env_or_exit(key):
    val = os.getenv(key)
    if val is None:
        logging.error(f"致命的エラー: 必須の環境変数 '{key}' が設定されていません。")
        sys.exit(1)
    return val

DATABASE_URL = f"mysql+pymysql://{get_env_or_exit('DB_USERNAME')}:{get_env_or_exit('DB_PASSWORD')}@{get_env_or_exit('DB_HOST')}:{get_env_or_exit('DB_PORT')}/{get_env_or_exit('DB_DATABASE')}"

# ==========================================
# 2. データベースモデル定義
# ==========================================
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
    local_logo_path = Column(String(255), nullable=True) # パイプライン用

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    manufacturer_id = Column(BigInteger, nullable=False)
    name = Column(String(255), nullable=False, unique=True)
    category = Column(String(50), nullable=True)
    displacement = Column(Integer, nullable=True)
    image_url = Column(String(255), nullable=True)
    local_image_path = Column(String(255), nullable=True) # パイプライン用
    # 照合で canonical のみを対象にするため参照（統合済みの重複行を再利用しない）。
    merged_into_id = Column(BigInteger, nullable=True)

class BikeModelIdentifier(Base):
    __tablename__ = "bike_model_identifiers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    bike_model_id = Column(BigInteger, ForeignKey("bike_models.id", ondelete="CASCADE"), nullable=False)
    site_id = Column(BigInteger, ForeignKey("sites.id", ondelete="CASCADE"), nullable=False)
    identifier = Column(String(100), nullable=False)
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_site_identifier_uc'),)

# ==========================================
# 3. Scrapy Pipeline (DB保存処理)
# ==========================================
class DatabasePipeline:
    """
    抽出したデータをDBに保存し、生成されたIDをアイテムに付与して
    後続の ImagePipeline に渡す役割。
    """
    def open_spider(self, spider):
        self.engine = create_engine(DATABASE_URL, pool_pre_ping=True)
        self.Session = sessionmaker(bind=self.engine)
        self.session = self.Session()

    def process_item(self, item, spider):
        # 中断信号が出ている場合はDB処理をスキップ
        if not spider.crawler.engine.running:
            return item

        try:
            if item.get('type') == 'manufacturer':
                self.save_manufacturer(item, spider)
            elif item.get('type') == 'bike_model':
                self.save_bike_model(item, spider)
            
            self.session.commit()
        except Exception as e:
            self.session.rollback()
            spider.logger.error(f"Pipeline DB Error: {e}")
            
        return item # 次のパイプライン（ImagePipeline）へ

    def save_manufacturer(self, item, spider):
        m_name = normalize_name(item['name'])
        m_record = self.session.query(Manufacturer).filter(Manufacturer.name == m_name).first()
        if not m_record:
            m_record = Manufacturer(name=m_name, logo_url=item['image_urls'][0] if item.get('image_urls') else None, country="不明")
            self.session.add(m_record)
            self.session.flush()
        
        spider.manufacturer_cache[m_name] = m_record.id
        item['id'] = m_record.id
        item['target_type'] = 'manufacturer'

    def save_bike_model(self, item, spider):
        model_name = normalize_name(item['name'])
        inferred_displacement = extract_displacement(item['name'])
        
        m_id = spider.manufacturer_cache.get(normalize_name(item['maker_name']))
        if not m_id: return

        # 1) 正規化名で完全一致（indexあり・高速）。
        model_record = self.session.query(BikeModel).filter(BikeModel.name == model_name).first()
        # 2) 外れたら表記ゆれ（ダッシュ/中黒/区切り違い）対策として、メーカー内の
        #    canonical 車種を照合キーで走査し、一致すれば再利用（重複作成を防ぐ）。
        if not model_record:
            match_key = model_match_key(model_name)
            for cand in self.session.query(BikeModel).filter(
                BikeModel.manufacturer_id == m_id,
                BikeModel.merged_into_id.is_(None),
            ).all():
                if model_match_key(cand.name) == match_key:
                    model_record = cand
                    # 2段目（キー照合）での再利用は、別車種を誤って統合する可能性があるため必ずログに残す。
                    spider.logger.info(
                        f"[dedup] キー照合で既存車種を再利用: maker_id={m_id} "
                        f"入力='{model_name}' -> 既存 id={cand.id} name='{cand.name}' key='{match_key}'"
                    )
                    break

        if not model_record:
            model_record = BikeModel(
                name=model_name,
                manufacturer_id=m_id,
                image_url=item['image_urls'][0] if item.get('image_urls') else None,
                displacement=inferred_displacement,
                category="不明"
            )
            self.session.add(model_record)
            self.session.flush()
        else:
            if item.get('image_urls') and not model_record.image_url:
                model_record.image_url = item['image_urls'][0]
            if inferred_displacement and not model_record.displacement:
                model_record.displacement = inferred_displacement

        item['id'] = model_record.id
        item['target_type'] = 'model'

        # 識別子の紐付け
        if spider.site_id and item.get('identifier'):
            exists = self.session.query(BikeModelIdentifier).filter(
                BikeModelIdentifier.site_id == spider.site_id,
                BikeModelIdentifier.identifier == item['identifier']
            ).first()
            if not exists:
                self.session.add(BikeModelIdentifier(
                    bike_model_id=model_record.id,
                    site_id=spider.site_id,
                    identifier=item['identifier']
                ))

    def close_spider(self, spider):
        self.session.close()

# ==========================================
# 4. Scrapy Spider
# ==========================================
class ModelSpider(scrapy.Spider):
    name = "model_collector"
    allowed_domains = ["www.bds-bikesensor.net", "cdn.bds-bikesensor.net", "images.bds-bikesensor.net"]
    start_urls = ["https://www.bds-bikesensor.net"]

    maker_list_raw = [
        {"slug": "honda", "name": "ホンダ"}, {"slug": "suzuki", "name": "スズキ"},
        {"slug": "yamaha", "name": "ヤマハ"}, {"slug": "kawasaki", "name": "カワサキ"},
        # ... (他のメーカーリストは以前と同様)
        {"slug": "bmw", "name": "BMW"}, {"slug": "ktm", "name": "KTM"},
        {"slug": "ducati", "name": "ドゥカティ"}, {"slug": "triumph", "name": "トライアンフ"},
        {"slug": "harley_davidson", "name": "ハーレーダビッドソン"},
    ]

    custom_settings = {
        'CONCURRENT_REQUESTS': 16,
        'DOWNLOAD_DELAY': 0.8,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {
            '__main__.DatabasePipeline': 300,            # 1. DBに保存してIDを取得
            'common.pipelines.MotoHubImagePipeline': 400, # 2. そのIDを使って画像を保存
        },
        'USER_AGENT': MOTOHUB_USER_AGENT,
        'ROBOTSTXT_OBEY': True,
    }

    def __init__(self, *args, **kwargs):
        super(ModelSpider, self).__init__(*args, **kwargs)
        engine = create_engine(DATABASE_URL)
        Session = sessionmaker(bind=engine)
        session = Session()

        site = session.query(Site).filter(Site.name == "BDS").first()
        if not site:
            site = Site(name="BDS", base_url="https://www.bds-bikesensor.net")
            session.add(site); session.commit()

        self.site_id = site.id
        self.manufacturer_cache = {m.name: m.id for m in session.query(Manufacturer).all()}
        session.close()

    def parse(self, response):
        """メーカーロゴとメーカーリンクの取得"""
        if not self.crawler.engine.running: return

        maker_containers = response.css('div.col.col-md-3, div.col.col-sm-4, div.col.col-6')
        for container in maker_containers:
            name_raw = container.css('p.text-center::text').get()
            if name_raw and '(' in name_raw:
                logo_url = container.css('span.c-delay_img::attr(data-src)').get()
                m_name = re.sub(r'\s*[\(\uff08].*?[\)\uff09]', '', name_raw).strip()
                if m_name:
                    yield {
                        'type': 'manufacturer',
                        'name': m_name,
                        'image_urls': [response.urljoin(logo_url)] if logo_url else []
                    }

        base_maker_url = "https://www.bds-bikesensor.net/bike/maker/"
        for maker in self.maker_list_raw:
            if not self.crawler.engine.running: break
            yield response.follow(
                url=base_maker_url + maker['slug'],
                callback=self.parse_models,
                meta={'maker_name': maker['name']},
                headers={'Referer': response.url}
            )

    def parse_models(self, response):
        """車種名とカタログ画像の取得"""
        if not self.crawler.engine.running: return

        maker_name = response.meta['maker_name']
        model_units = response.css('.c-search_name_block_wrap')

        for unit in model_units:
            if not self.crawler.engine.running: break

            identifier = unit.css('input.model-checkbox::attr(value)').get()
            model_name_raw = unit.css('.c-search_name_block_text::text').get()
            img_url = unit.css('img.c-delay_load::attr(src)').get() or unit.css('img.c-delay_load::attr(data-src)').get()

            if not model_name_raw or not identifier:
                continue

            model_name = re.sub(r'\s*[\(\uff08].*?[\)\uff09]', '', model_name_raw).strip()

            yield {
                'type': 'bike_model',
                'maker_name': maker_name,
                'name': model_name,
                'identifier': identifier,
                'image_urls': [response.urljoin(img_url)] if img_url else []
            }

    def closed(self, reason):
        if reason != 'finished':
            self.logger.info(f"Spider stopped by user ({reason}).")
        else:
            self.logger.info("Model collection completed successfully.")

def main():
    process = CrawlerProcess()
    process.crawl(ModelSpider)
    process.start()

if __name__ == "__main__":
    main()