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
# 1. 環境設定 & セキュリティ強化
# ==========================================
# ファイル位置からプロジェクトルートの .env を読み込む
current_dir = os.path.dirname(os.path.abspath(__file__))
env_path = os.path.join(current_dir, '..', '..', '.env')
load_dotenv(dotenv_path=env_path)

def get_env_or_exit(key, default=None, required=True):
    """環境変数を取得し、存在しない場合はセキュリティのためプログラムを終了する"""
    val = os.getenv(key, default)
    if required and val is None:
        logging.error(f"致命的エラー: 必須の環境変数 '{key}' が設定されていません。")
        sys.exit(1)
    return val

# DB接続情報は環境変数から取得（デフォルト値なしの必須項目）
DB_USER = get_env_or_exit("DB_USERNAME")
DB_PASS = get_env_or_exit("DB_PASSWORD")
DB_NAME = get_env_or_exit("DB_DATABASE")
DB_HOST = get_env_or_exit("DB_HOST", default="db")
DB_PORT = get_env_or_exit("DB_PORT", default="3306")

DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}"

# ==========================================
# 2. データベースモデル定義
# ==========================================
class Base(DeclarativeBase):
    pass

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(255), nullable=False)
    category = Column(String(50), nullable=True)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

# ==========================================
# 3. Scrapy Pipeline (DB更新処理)
# ==========================================
class CategorySyncPipeline:
    """
    スクレイピングした車種名とDB内の車種を照合し、カテゴリーを更新する
    """
    def open_spider(self, spider):
        self.engine = create_engine(DATABASE_URL)
        self.Session = sessionmaker(bind=self.engine)
        self.session = self.Session()
        
        # カテゴリーが未設定の車種のみをキャッシュにロード
        spider.logger.info("名寄せ用キャッシュを構築中...")
        models_to_update = self.session.query(BikeModel).filter(
            or_(BikeModel.category == None, BikeModel.category == "不明")
        ).all()
        
        self.model_cache = {}
        for m in models_to_update:
            if m.name not in self.model_cache:
                self.model_cache[m.name] = []
            self.model_cache[m.name].append(m.id)
        
        spider.logger.info(f"キャッシュ構築完了: {len(self.model_cache)}件の車種が同期対象です。")

    def process_item(self, item, spider):
        category_name = item['category_name']
        scraped_model_names = item['model_names']
        update_count = 0

        for full_text in scraped_model_names:
            # 括弧（台数表示など）を除去して車種名のみにする
            model_name = re.sub(r'\s*[\(\uff08].*', '', full_text).strip()
            if not model_name:
                continue
            
            # キャッシュに存在する車種であればDBを更新
            target_ids = self.model_cache.get(model_name, [])
            for t_id in target_ids:
                try:
                    self.session.execute(
                        update(BikeModel).where(BikeModel.id == t_id).values(category=category_name)
                    )
                    update_count += 1
                except Exception as e:
                    spider.logger.error(f"ID {t_id} の更新に失敗: {e}")

        if update_count > 0:
            self.session.commit()
            spider.logger.info(f"カテゴリー '{category_name}': {update_count}件の車種を更新しました。")
        
        return item

    def close_spider(self, spider):
        self.session.close()

# ==========================================
# 4. Scrapy Spider
# ==========================================
class CategorySpider(scrapy.Spider):
    name = "bds_category_collector"
    allowed_domains = ["www.bds-bikesensor.net"]
    
    # 対象カテゴリーの定義
    CATEGORIES = [
        {"slug": "gentsuki", "name": "原付スクーター"},
        {"slug": "scooter51_125", "name": "スクーター/51～125cc"},
        {"slug": "big_scooter", "name": "スクーター/126cc以上"},
        {"slug": "naked", "name": "ネイキッド"},
        {"slug": "sports", "name": "スポーツ/レプリカ"},
        {"slug": "classic", "name": "クラシック"},
        {"slug": "offroad", "name": "オフロード"},
        {"slug": "american", "name": "アメリカン"},
        {"slug": "tourer", "name": "ツアラー"},
        {"slug": "adventure", "name": "アドベンチャー"},
        {"slug": "streetfighter", "name": "ストリートファイター"},
        {"slug": "minibike", "name": "ミニバイク"},
        {"slug": "ev", "name": "EV"},
        {"slug": "other", "name": "その他"}
    ]

    custom_settings = {
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 0.5,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {'__main__.CategorySyncPipeline': 300},
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    }

    def start_requests(self):
        base_url = "https://www.bds-bikesensor.net/bike/type/"
        for cat in self.CATEGORIES:
            yield scrapy.Request(
                url=base_url + cat['slug'],
                callback=self.parse,
                meta={'category_name': cat['name']}
            )

    def parse(self, response):
        """車種名ブロックからテキストを取得"""
        category_name = response.meta['category_name']
        # セレクタ: 車種名が表示されている要素
        model_names = response.css(".c-search_name_block_text::text").getall()
        
        if model_names:
            yield {
                'category_name': category_name,
                'model_names': model_names
            }

# ==========================================
# 5. 実行ブロック
# ==========================================
def main():
    process = CrawlerProcess()
    process.crawl(CategorySpider)
    process.start()

if __name__ == "__main__":
    main()