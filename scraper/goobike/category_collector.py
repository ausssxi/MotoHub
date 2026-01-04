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

# 同時接続数を制限（一度に4ジャンル程度が安全）
MAX_CONCURRENT_PAGES = 4
semaphore = asyncio.Semaphore(MAX_CONCURRENT_PAGES)

async def block_resources(route):
    """画像、CSS、フォントなどの不要なリソースを遮断して高速化"""
    if route.request.resource_type in ["image", "media", "font", "stylesheet"]:
        await route.abort()
    else:
        await route.continue_()

async def process_genre(context, genre_id, base_url, model_cache):
    """特定のジャンルページを解析してカテゴリーを更新するタスク"""
    async with semaphore:
        db = SessionLocal()
        page = await context.new_page()
        await page.route("**/*", block_resources)

        genre_str = str(genre_id).zfill(2)
        genre_url = f"{base_url}/genre-{genre_str}/index.html"
        
        try:
            print(f"  [開始] ジャンル {genre_str}")
            await page.goto(genre_url, wait_until="domcontentloaded", timeout=60000)
            
            # スタイル名の取得
            style_elem = await page.query_selector("li strong")
            if not style_elem:
                return
            style_name = (await style_elem.inner_text()).strip()
            
            # ページ内の車種名（bタグ）を一括取得
            bike_elements = await page.query_selector_all("li.bike_list em b")
            
            update_count = 0
            for bike_elem in bike_elements:
                raw_name = await bike_elem.inner_text()
                model_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', raw_name).strip()
                if not model_name:
                    continue
                
                # キャッシュから車種レコードを取得（DBへのSELECTを回避）
                targets = model_cache.get(model_name, [])
                
                for t_id in targets:
                    # Sessionを介してオブジェクトを再取得して更新
                    model_obj = db.query(BikeModel).get(t_id)
                    if model_obj and (model_obj.category is None or model_obj.category == "不明"):
                        model_obj.category = style_name
                        update_count += 1
            
            db.commit()
            if update_count > 0:
                print(f"  [完了] ジャンル {genre_str} ({style_name}): {update_count}件更新")
        except Exception as e:
            print(f"  [エラー] ジャンル {genre_str}: {e}")
            db.rollback()
        finally:
            db.close()
            await page.close()

async def collect():
    async with async_playwright() as p:
        print("GooBikeカテゴリー同期（高速版）を開始します...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        
        base_url = "https://www.goobike.com"
        db = SessionLocal()

        # インメモリキャッシュの構築
        # カテゴリーが未設定の車種のみを対象に、名前からIDのリストを引けるようにする
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

        # 1から16までのジャンルを並列処理
        tasks = [
            process_genre(context, i, base_url, model_cache)
            for i in range(1, 17)
        ]
        
        await asyncio.gather(*tasks)

        print("\nGooBikeカテゴリー同期が完了しました。")
        await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())