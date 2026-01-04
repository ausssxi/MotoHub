import asyncio
import os
import datetime
import re
import sys
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, or_
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# 1. 環境変数の読み込み
# 現在のファイル位置（scraper/bds/）から見て、2つ上の階層（scraper/）にある .env を探す
current_dir = os.path.dirname(os.path.abspath(__file__))
env_path = os.path.join(current_dir, '..', '..', '.env')
load_dotenv(dotenv_path=env_path)

# もし読み込めなかったらカレントディレクトリも確認
if not os.getenv("DB_DATABASE"):
    load_dotenv()

def get_env_or_exit(key, default=None, required=True):
    """
    環境変数を取得する。
    required=True の場合、値が取得できなければプログラムを終了させる（セキュリティ対策）。
    """
    val = os.getenv(key, default)
    if required and val is None:
        print(f"致命的エラー: 必須の環境変数 '{key}' が設定されていません。")
        sys.exit(1)
    return val

# データベース接続設定: 機密情報はデフォルト値を設定せず必須（required=True）とする
DB_USER = get_env_or_exit("DB_USERNAME")
DB_PASS = get_env_or_exit("DB_PASSWORD")
DB_NAME = get_env_or_exit("DB_DATABASE")

# 接続先やポートは、機密情報ではないため利便性のためにデフォルト値を残しても許容される
DB_HOST = get_env_or_exit("DB_HOST", default="db")
DB_PORT = get_env_or_exit("DB_PORT", default="3306")

DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}"

engine = create_engine(DATABASE_URL)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

class Base(DeclarativeBase):
    pass

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(255), nullable=False)
    category = Column(String(50), nullable=True)

# 同時接続数を制限
MAX_CONCURRENT_PAGES = 5
semaphore = asyncio.Semaphore(MAX_CONCURRENT_PAGES)

async def block_resources(route):
    """画像、CSS、フォントなどの不要なリソースを遮断して高速化"""
    if route.request.resource_type in ["image", "media", "font", "stylesheet"]:
        await route.abort()
    else:
        await route.continue_()

async def process_category(context, cat_info, base_url, model_cache):
    """特定のカテゴリーページを解析して車種のカテゴリーを更新するタスク"""
    async with semaphore:
        db = SessionLocal()
        page = await context.new_page()
        await page.route("**/*", block_resources)

        target_url = f"{base_url}/bike/type/{cat_info['slug']}"
        
        try:
            print(f"  [開始] {cat_info['name']}")
            await page.goto(target_url, wait_until="domcontentloaded", timeout=60000)
            
            # 車種リストの描画を待機
            try:
                await page.wait_for_selector(".c-search_name_block_text", timeout=10000)
            except:
                return

            # ページ内の車種名ブロックを一括取得
            name_elements = await page.query_selector_all(".c-search_name_block_text")
            
            update_count = 0
            for name_el in name_elements:
                full_text = (await name_el.inner_text()).strip()
                model_name = re.sub(r'\s*[\(\uff08].*', '', full_text).strip()
                
                if not model_name:
                    continue

                # キャッシュから該当する車種IDリストを取得
                targets = model_cache.get(model_name, [])
                
                for t_id in targets:
                    model_obj = db.query(BikeModel).get(t_id)
                    if model_obj and (model_obj.category is None or model_obj.category == "不明"):
                        model_obj.category = cat_info['name']
                        update_count += 1
            
            db.commit()
            if update_count > 0:
                print(f"  [完了] {cat_info['name']}: {update_count}件更新")
        except Exception as e:
            print(f"  [エラー] {cat_info['name']}: {e}")
            db.rollback()
        finally:
            db.close()
            await page.close()

async def collect():
    async with async_playwright() as p:
        print("BDSカテゴリー同期（セキュア版）を開始します...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        
        base_url = "https://www.bds-bikesensor.net"
        db = SessionLocal()

        print("キャッシュを構築中...")
        all_models = db.query(BikeModel).filter(
            or_(BikeModel.category == None, BikeModel.category == "不明")
        ).all()
        
        model_cache = {}
        for m in all_models:
            if m.name not in model_cache:
                model_cache[m.name] = []
            model_cache[m.name].append(m.id)
        
        db.close()

        categories = [
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

        tasks = [
            process_category(context, cat, base_url, model_cache)
            for cat in categories
        ]
        
        await asyncio.gather(*tasks)

        print("\nBDSカテゴリー同期が完了しました。")
        await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())