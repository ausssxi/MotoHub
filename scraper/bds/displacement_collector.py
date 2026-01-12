import scrapy
from scrapy.crawler import CrawlerProcess
import os
import re
import sys
import datetime
import logging
import unicodedata
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, or_, update
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# ==========================================
# 1. 環境設定 & セキュリティ強化
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
env_path = os.path.join(current_dir, '..', '..', '.env')
load_dotenv(dotenv_path=env_path)

def get_env_or_exit(key, default=None, required=True):
    """環境変数を取得し、存在しない場合はプログラムを終了する"""
    val = os.getenv(key, default)
    if required and val is None:
        logging.error(f"致命的エラー: 必須の環境変数 '{key}' が設定されていません。")
        sys.exit(1)
    return val

# DB接続設定
DB_USER = get_env_or_exit("DB_USERNAME")
DB_PASS = get_env_or_exit("DB_PASSWORD")
DB_NAME = get_env_or_exit("DB_DATABASE")
DB_HOST = get_env_or_exit("DB_HOST", default="db")
DB_PORT = get_env_or_exit("DB_PORT", default="3306")

DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}"

# ==========================================
# 2. ユーティリティ & モデル定義
# ==========================================
def robust_normalize(text):
    """文字のゆれを排除する"""
    if not text:
        return ""
    text = unicodedata.normalize('NFKC', text)
    text = text.upper()
    text = re.sub(r'[ー－―—‐-]', '-', text)
    return text.strip()

class Base(DeclarativeBase):
    pass

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    name = Column(String(255), nullable=False, unique=True)
    displacement = Column(Integer, nullable=True)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

# ==========================================
# 3. Scrapy Pipeline (DB更新処理)
# ==========================================
class DisplacementPipeline:
    """取得した排気量データをDBに反映する"""
    def open_spider(self, spider):
        self.engine = create_engine(DATABASE_URL)
        self.Session = sessionmaker(bind=self.engine)
        self.session = self.Session()
        
        # 更新が必要な車種（排気量が未設定のもの）をキャッシュ
        spider.logger.info("未設定モデルのキャッシュを構築中...")
        all_models = self.session.query(BikeModel).filter(
            or_(BikeModel.displacement == None, BikeModel.displacement == 0)
        ).all()
        
        # Spider側で照合できるように正規化名をキーにした辞書を作成
        spider.model_cache = {robust_normalize(m.name): {"id": m.id, "name": m.name} for m in all_models}
        spider.logger.info(f"キャッシュ構築完了: {len(spider.model_cache)} 件が対象です。")

    def process_item(self, item, spider):
        model_id = item.get('model_id')
        disp_val = item.get('displacement')
        
        if model_id and disp_val:
            try:
                self.session.execute(
                    update(BikeModel).where(BikeModel.id == model_id).values(displacement=disp_val)
                )
                self.session.commit()
                spider.logger.info(f"  [更新成功] {item['model_name']} -> {disp_val}cc")
            except Exception as e:
                self.session.rollback()
                spider.logger.error(f"  [更新失敗] {item['model_name']}: {e}")
        return item

    def close_spider(self, spider):
        self.session.close()

# ==========================================
# 4. Scrapy Spider
# ==========================================
class DisplacementSpider(scrapy.Spider):
    name = "bds_displacement_collector"
    allowed_domains = ["www.bds-bikesensor.net"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 16,  # Playwrightのセマフォに相当
        'DOWNLOAD_DELAY': 0.5,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {'__main__.DisplacementPipeline': 300},
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    }

    maker_list = [
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

    def start_requests(self):
        # キャッシュが空（＝全車種登録済み）なら何もしない
        if not hasattr(self, 'model_cache') or not self.model_cache:
            self.logger.info("更新が必要な車種が見つかりませんでした。")
            return

        base_url = "https://www.bds-bikesensor.net/bike/maker/"
        for maker in self.maker_list:
            yield scrapy.Request(
                url=base_url + maker['slug'],
                callback=self.parse_maker_page,
                meta={'maker_name': maker['name']}
            )

    def parse_maker_page(self, response):
        """メーカー別の車種一覧から、詳細ページへのリンクを抽出"""
        model_items = response.css(".model_item")
        
        for item in model_items:
            m_link = item.css("a.c-bike_image")
            if not m_link:
                continue
            
            raw_title = m_link.attrib.get('title', '').strip()
            norm_title = robust_normalize(raw_title)
            href = m_link.attrib.get('href')
            
            if norm_title in self.model_cache and href:
                model_data = self.model_cache[norm_title]
                yield response.follow(
                    url=href,
                    callback=self.parse_detail_page,
                    meta={
                        'model_id': model_data['id'],
                        'model_name': model_data['name']
                    }
                )

    def parse_detail_page(self, response):
        """車両個別ページから排気量を抽出"""
        model_id = response.meta['model_id']
        model_name = response.meta['model_name']
        
        # 排気量情報の抽出
        status_cols = response.css(".c-search_status_col")
        disp_val = None
        
        for col in status_cols:
            head_text = col.css(".c-search_status_head::text").get()
            if head_text and "排気量" in head_text:
                val_text = col.css(".c-search_status_title01::text").get()
                if val_text:
                    match = re.search(r'(\d+)', val_text)
                    if match:
                        disp_val = int(match.group(1))
                        break
        
        if disp_val:
            yield {
                'model_id': model_id,
                'model_name': model_name,
                'displacement': disp_val
            }

# ==========================================
# 5. 実行ブロック
# ==========================================
def main():
    logging.getLogger('scrapy').setLevel(logging.INFO)
    process = CrawlerProcess()
    process.crawl(DisplacementSpider)
    process.start()

if __name__ == "__main__":
    main()