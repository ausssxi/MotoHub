import asyncio
import os
import datetime
import re
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, or_
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# 環境変数の読み込み
load_dotenv()
if not os.getenv("DB_DATABASE"):
    load_dotenv(dotenv_path='../../.env')

# データベース接続設定
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

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(255), nullable=False)
    category = Column(String(50), nullable=True)

# 同時接続数を制限（一度に5カテゴリー程度が効率的）
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
        # リソース制限の適用
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
                # "(7台)" などの余計な文字を削除
                model_name = re.sub(r'\s*[\(\uff08].*', '', full_text).strip()
                
                if not model_name:
                    continue

                # キャッシュから該当する車種IDリストを取得
                targets = model_cache.get(model_name, [])
                
                for t_id in targets:
                    # DBからレコードを取得して更新
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
        print("BDSカテゴリー同期（高速版）を開始します...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        
        base_url = "https://www.bds-bikesensor.net"
        db = SessionLocal()

        # インメモリキャッシュの構築
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

        # カテゴリーとスラッグの定義
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

        # 並列処理の実行
        tasks = [
            process_category(context, cat, base_url, model_cache)
            for cat in categories
        ]
        
        await asyncio.gather(*tasks)

        print("\nBDSカテゴリー同期が完了しました。")
        await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())