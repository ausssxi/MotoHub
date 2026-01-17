import scrapy
from scrapy.crawler import CrawlerProcess
import os
import re
import sys
import logging
import datetime
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, ForeignKey, UniqueConstraint
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# ==========================================
# 0. 親ディレクトリにある utils.py を読み込むための設定
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
sys.path.append(parent_dir)

# 排気量抽出ロジックをインポート
from utils import normalize_name, extract_displacement

# ==========================================
# 1. 環境設定 & セキュリティ強化
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
    site_id = Column(BigInteger, ForeignKey("sites.id", ondelete="CASCADE"), nullable=False)
    identifier = Column(String(100), nullable=False)
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_site_identifier_uc'),)

# ==========================================
# 3. Scrapy Pipeline (DB保存処理)
# ==========================================
class DatabasePipeline:
    def open_spider(self, spider):
        self.engine = create_engine(DATABASE_URL)
        self.Session = sessionmaker(bind=self.engine)
        self.session = self.Session()

    def process_item(self, item, spider):
        if item.get('type') == 'manufacturer':
            self.save_manufacturer(item, spider)
        elif item.get('type') == 'bike_model':
            self.save_bike_model(item, spider)
        return item

    def save_manufacturer(self, item, spider):
        m_name = normalize_name(item['name'])
        try:
            m_record = self.session.query(Manufacturer).filter(Manufacturer.name == m_name).first()
            if not m_record:
                m_record = Manufacturer(name=m_name, logo_url=item['logo_url'], country="不明")
                self.session.add(m_record)
                self.session.commit()
                spider.manufacturer_cache[m_name] = m_record.id
            else:
                spider.manufacturer_cache[m_name] = m_record.id
                if item['logo_url'] and not m_record.logo_url:
                    m_record.logo_url = item['logo_url']
                    self.session.commit()
        except IntegrityError:
            self.session.rollback()
            m_record = self.session.query(Manufacturer).filter(Manufacturer.name == m_name).first()
            if m_record:
                spider.manufacturer_cache[m_name] = m_record.id
        except Exception as e:
            self.session.rollback()
            spider.logger.error(f"Manufacturer save error: {e}")

    def save_bike_model(self, item, spider):
        model_name = normalize_name(item['name'])
        # 排気量を抽出
        inferred_displacement = extract_displacement(item['name'])
        
        try:
            m_id = item.get('manufacturer_id') or spider.manufacturer_cache.get(normalize_name(item['maker_name']))
            
            if not m_id:
                m_record = self.session.query(Manufacturer).filter(Manufacturer.name == normalize_name(item['maker_name'])).first()
                if m_record:
                    m_id = m_record.id
                    spider.manufacturer_cache[normalize_name(item['maker_name'])] = m_id
                else:
                    return

            model_record = self.session.query(BikeModel).filter(BikeModel.name == model_name).first()
            if not model_record:
                model_record = BikeModel(
                    name=model_name,
                    manufacturer_id=m_id,
                    image_url=item['image_url'],
                    displacement=inferred_displacement,
                    category="不明"
                )
                self.session.add(model_record)
                self.session.commit()
                model_id = model_record.id
            else:
                model_id = model_record.id
                needs_update = False
                if item['image_url'] and not model_record.image_url:
                    model_record.image_url = item['image_url']
                    needs_update = True
                if inferred_displacement and not model_record.displacement:
                    model_record.displacement = inferred_displacement
                    needs_update = True
                
                if needs_update:
                    self.session.commit()

            if spider.site_id and item['identifier']:
                exists = self.session.query(BikeModelIdentifier).filter(
                    BikeModelIdentifier.site_id == spider.site_id,
                    BikeModelIdentifier.identifier == item['identifier']
                ).first()
                if not exists:
                    self.session.add(BikeModelIdentifier(
                        bike_model_id=model_id,
                        site_id=spider.site_id,
                        identifier=item['identifier']
                    ))
                    self.session.commit()
        except IntegrityError:
            self.session.rollback()
        except Exception as e:
            self.session.rollback()
            spider.logger.error(f"BikeModel save error: {e}")

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
        'CONCURRENT_REQUESTS': 16,
        'DOWNLOAD_DELAY': 0.8,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {
            '__main__.DatabasePipeline': 300,
        },
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    }

    def __init__(self, *args, **kwargs):
        super(ModelSpider, self).__init__(*args, **kwargs)
        engine = create_engine(DATABASE_URL)
        Session = sessionmaker(bind=engine)
        session = Session()

        target_site_name = "BDS"
        site = session.query(Site).filter(Site.name == target_site_name).first()
        if not site:
            try:
                site = Site(name=target_site_name, base_url="https://www.bds-bikesensor.net")
                session.add(site)
                session.commit()
            except IntegrityError:
                session.rollback()
                site = session.query(Site).filter(Site.name == target_site_name).first()

        self.site_id = site.id
        self.manufacturer_cache = {m.name: m.id for m in session.query(Manufacturer).all()}
        session.close()

    def parse(self, response):
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
                        'logo_url': response.urljoin(logo_url) if logo_url else None
                    }

        base_maker_url = "https://www.bds-bikesensor.net/bike/maker/"
        for maker in self.maker_list_raw:
            yield response.follow(
                url=base_maker_url + maker['slug'],
                callback=self.parse_models,
                meta={'maker_name': maker['name']},
                headers={'Referer': response.url}
            )

    def parse_models(self, response):
        maker_name = response.meta['maker_name']
        model_units = response.css('.c-search_name_block_wrap')

        for unit in model_units:
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
                'image_url': response.urljoin(img_url) if img_url else None
            }

def main():
    logging.getLogger('scrapy').setLevel(logging.INFO)
    process = CrawlerProcess()
    process.crawl(ModelSpider)
    process.start()

if __name__ == "__main__":
    main()