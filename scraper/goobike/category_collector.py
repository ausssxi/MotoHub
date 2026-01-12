import scrapy
from scrapy.crawler import CrawlerProcess
import os
import re
import sys
import datetime
import logging
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, BigInteger, String, DateTime, or_, update
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# ==========================================
# 1. データベース設定 & モデル定義
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
# プロジェクトルートの .env を読み込む (scraper/goobike/ から見て ../../.env)
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

class Base(DeclarativeBase):
    pass

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(255), nullable=False)
    category = Column(String(50), nullable=True)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

# ==========================================
# 2. Scrapy Pipeline (DB更新処理)
# ==========================================
class CategoryPipeline:
    def open_spider(self, spider):
        self.engine = create_engine(DATABASE_URL)
        self.Session = sessionmaker(bind=self.engine)
        self.session = self.Session()
        
        # カテゴリー未設定の車種をインメモリキャッシュに展開
        spider.logger.info("名寄せ用キャッシュを構築中...")
        all_models = self.session.query(BikeModel).filter(
            or_(BikeModel.category == None, BikeModel.category == "不明")
        ).all()
        
        self.model_cache = {}
        for m in all_models:
            if m.name not in self.model_cache:
                self.model_cache[m.name] = []
            self.model_cache[m.name].append(m.id)
        
        spider.logger.info(f"キャッシュ構築完了: {len(self.model_cache)}件の車種が対象です。")

    def process_item(self, item, spider):
        style_name = item['style_name']
        model_names = item['model_names']
        update_count = 0

        for raw_name in model_names:
            # 括弧内の排気量などを除去して車種名のみにする
            model_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', raw_name).strip()
            if not model_name:
                continue
            
            # キャッシュから車種IDを取得
            target_ids = self.model_cache.get(model_name, [])
            for t_id in target_ids:
                try:
                    # カテゴリーを更新
                    self.session.execute(
                        update(BikeModel).where(BikeModel.id == t_id).values(category=style_name)
                    )
                    update_count += 1
                except Exception as e:
                    spider.logger.error(f"Update error for ID {t_id}: {e}")

        if update_count > 0:
            self.session.commit()
            spider.logger.info(f"ジャンル '{style_name}': {update_count}件の車種カテゴリーを更新しました。")
        
        return item

    def close_spider(self, spider):
        self.session.close()

# ==========================================
# 3. Scrapy Spider (巡回ロジック)
# ==========================================
class GoobikeCategorySpider(scrapy.Spider):
    name = "goobike_category_spider"
    allowed_domains = ["www.goobike.com"]
    
    def start_requests(self):
        # 1から16までのジャンルページを生成
        base_url = "https://www.goobike.com/genre-{:02d}/index.html"
        for i in range(1, 17):
            yield scrapy.Request(base_url.format(i), callback=self.parse)

    custom_settings = {
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 0.5,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {'__main__.CategoryPipeline': 300},
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    }

    def parse(self, response):
        """ジャンルページからスタイル名と車種リストを取得"""
        # スタイル名の取得 (例: ネイキッド、スーパースポーツなど)
        style_name = response.css('li strong::text').get()
        if not style_name:
            # フォールバック: パンくずリストやh1から探す
            style_name = response.css('h1::text').get()
        
        if style_name:
            style_name = style_name.strip()
            # 車種名（bタグ）を一括取得
            model_names = response.css('li.bike_list em b::text').getall()
            
            yield {
                'style_name': style_name,
                'model_names': model_names
            }

# ==========================================
# 4. 実行ブロック
# ==========================================
if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(GoobikeCategorySpider)
    process.start()