import scrapy
from scrapy.crawler import CrawlerProcess
import os
import re
import sys
import datetime
import logging
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, update
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# ==========================================
# 0. パス調整 & 共通ユーティリティ読込
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir) # scraper フォルダ
sys.path.append(parent_dir)

from utils import normalize_name, extract_displacement
from common.base_spider import MOTOHUB_USER_AGENT

# ==========================================
# 1. データベース設定 & モデル定義
# ==========================================
env_path = os.path.join(parent_dir, '..', '.env')
load_dotenv(dotenv_path=env_path)

def get_env_or_exit(key, default=None):
    val = os.getenv(key, default)
    if val is None:
        logging.error(f"致命的エラー: 必須の環境変数 '{key}' が設定されていません。")
        sys.exit(1)
    return val

DB_USER = get_env_or_exit("DB_USERNAME")
DB_PASS = get_env_or_exit("DB_PASSWORD")
DB_NAME = get_env_or_exit("DB_DATABASE")
DB_HOST = get_env_or_exit("DB_HOST", default="db")
DB_PORT = get_env_or_exit("DB_PORT", default="3306")

DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}"

engine = create_engine(DATABASE_URL, pool_pre_ping=True)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

class Base(DeclarativeBase):
    pass

# ★追加: カテゴリテーブルの定義
class Category(Base):
    __tablename__ = "categories"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(255), unique=True)
    slug = Column(String(255))

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(255), nullable=False)
    
    # ★修正: category(文字列)ではなく category_id(外部キー) を更新対象にする
    category_id = Column(BigInteger, nullable=True)
    
    displacement = Column(Integer, nullable=True)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

# ==========================================
# 2. Scrapy Pipeline (DB更新処理)
# ==========================================
class CategoryPipeline:
    # GooBikeのジャンル名とDBのカテゴリ名の対応表
    # { "GooBike上の名前": "DB上の名前" }
    CATEGORY_MAPPING = {
        "原付スクーター": "スクーター",
        "ミニバイク": "ミニバイク",
        "ストリート": "ネイキッド", # ストリートはネイキッドに分類（またはその他）
        "ネイキッド": "ネイキッド",
        "アメリカン": "アメリカン",
        "スポーツ・レプリカ": "スーパースポーツ", # 表記ゆれ対応
        "ツアラー": "ツアラー",
        "ビッグスクーター": "スクーター", # 一旦スクーターに統合
        "オフロード": "オフロード",
        "輸入車": "その他",      # 輸入車はカテゴリではないためその他へ
        "コンペティション": "オフロード",
        "ＥＶバイク": "電動バイク",
        "電動スクーター": "電動バイク",
        "その他": "その他",
        "ビジネスバイク": "ビジネス", # もしあれば
    }

    def open_spider(self, spider):
        spider.logger.info("DB接続とキャッシュ構築を開始...")
        self.session = SessionLocal()
        
        # 1. カテゴリIDのキャッシュ作成
        # DBにあるカテゴリ名(name)からID(id)を引ける辞書を作る
        categories = self.session.query(Category).all()
        self.cat_id_map = {cat.name: cat.id for cat in categories}
        spider.logger.info(f"カテゴリ定義をロード: {self.cat_id_map}")

        # 2. 車種IDのキャッシュ作成
        all_models = self.session.query(BikeModel).all()
        self.model_cache = {}
        for m in all_models:
            norm_key = normalize_name(m.name)
            if norm_key not in self.model_cache:
                self.model_cache[norm_key] = []
            self.model_cache[norm_key].append(m.id)
        
        spider.logger.info(f"車種キャッシュ構築完了: {len(self.model_cache)}件")

    def process_item(self, item, spider):
        goobike_style_name = item['style_name']
        model_names = item['model_names']
        
        # マッピングを使ってDB上のカテゴリ名を決定
        # マッピングになければそのままの名前を使ってみる
        db_cat_name = self.CATEGORY_MAPPING.get(goobike_style_name, goobike_style_name)
        
        # カテゴリIDを取得
        category_id = self.cat_id_map.get(db_cat_name)
        
        if not category_id:
            spider.logger.warning(f"⚠️ カテゴリIDが見つかりません: '{goobike_style_name}' -> '{db_cat_name}' (DB未登録)")
            # IDがない場合は更新できないのでスキップ（または 'その他' のIDを入れる等の対応も可）
            return item

        update_count = 0

        for raw_name in model_names:
            clean_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', raw_name).strip()
            norm_name = normalize_name(clean_name)
            inferred_disp = extract_displacement(raw_name)
            
            target_ids = self.model_cache.get(norm_name, [])
            
            for t_id in target_ids:
                try:
                    # ★修正: category_id を更新する
                    update_values = {"category_id": category_id}
                    
                    if inferred_disp:
                        model_obj = self.session.query(BikeModel).get(t_id)
                        if model_obj and not model_obj.displacement:
                            update_values["displacement"] = inferred_disp
                    
                    self.session.execute(
                        update(BikeModel).where(BikeModel.id == t_id).values(**update_values)
                    )
                    update_count += 1
                except Exception as e:
                    spider.logger.error(f"Update error for ID {t_id}: {e}")

        if update_count > 0:
            self.session.commit()
            spider.logger.info(f"ジャンル '{goobike_style_name}' (ID:{category_id}): {update_count}件を更新しました。")
        
        return item

    def close_spider(self, spider):
        self.session.close()

# ==========================================
# 3. Scrapy Spider (巡回ロジック)
# ==========================================
class GoobikeCategorySpider(scrapy.Spider):
    name = "goobike_category_collector"
    allowed_domains = ["www.goobike.com"]
    
    def start_requests(self):
        # ジャンル1から16までのページを生成
        base_url = "https://www.goobike.com/genre-{:02d}/index.html"
        for i in range(1, 17):
            yield scrapy.Request(base_url.format(i), callback=self.parse)

    custom_settings = {
        'CONCURRENT_REQUESTS': 4,
        'DOWNLOAD_DELAY': 1.0,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {'__main__.CategoryPipeline': 300},
        'USER_AGENT': MOTOHUB_USER_AGENT,
        'ROBOTSTXT_OBEY': True,
    }

    def parse(self, response):
        """ジャンルページからスタイル名と車種リストを取得"""
        # スタイル名 (例: ネイキッド)
        style_name = response.css('li strong::text').get() or response.css('h1::text').get()
        
        if style_name:
            style_name = style_name.strip()
            # 【重要】GooBikeの正しいセレクタ
            model_names = response.css('li.bike_list em b::text').getall()
            
            yield {
                'style_name': style_name,
                'model_names': model_names
            }

if __name__ == "__main__":
    print(">>> GooBike Category Collector (ID Update Ver) Started.")
    logging.basicConfig(level=logging.INFO)
    
    process = CrawlerProcess()
    process.crawl(GoobikeCategorySpider)
    process.start()