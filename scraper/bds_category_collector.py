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
    load_dotenv(dotenv_path='../.env')

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

async def collect_bds_categories():
    async with async_playwright() as p:
        print("BDSカテゴリー同期を開始します（固定リンク方式）...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        page = await context.new_page()
        base_url = "https://www.bds-bikesensor.net"
        db = SessionLocal()

        # ソースコードにカテゴリーとスラッグを直接定義
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

        try:
            # 各カテゴリーページを順次巡回
            for cat in categories:
                target_url = f"{base_url}/bike/type/{cat['slug']}"
                print(f"  カテゴリー解析中: {cat['name']} ({target_url})")
                
                try:
                    # ページの骨組みができるまで待機
                    await page.goto(target_url, wait_until="domcontentloaded", timeout=60000)
                    
                    # 車種名が描画されるのを待つ (少し時間がかかる場合があるため)
                    try:
                        await page.wait_for_selector(".c-search_name_block_text", timeout=10000)
                    except:
                        print(f"    [SKIP] 車種リストが見つかりませんでした")
                        continue

                    # 車種名ブロックを取得
                    # 例: <h3 class="c-search_name_block_text"> BJ (7台)</h3>
                    name_elements = await page.query_selector_all(".c-search_name_block_text")
                    
                    update_count = 0
                    for name_el in name_elements:
                        full_text = (await name_el.inner_text()).strip()
                        # "BJ (7台)" の形式から "BJ" を抽出
                        model_name = re.sub(r'\s*[\(\uff08].*', '', full_text).strip()
                        
                        if not model_name: continue

                        # カテゴリーが空または不明のレコードのみ更新対象とする
                        targets = db.query(BikeModel).filter(
                            BikeModel.name == model_name,
                            or_(BikeModel.category == None, BikeModel.category == "不明")
                        ).all()
                        
                        for t in targets:
                            t.category = cat['name']
                            update_count += 1
                    
                    db.commit()
                    if update_count > 0:
                        print(f"    -> {update_count} 件の車種を '{cat['name']}' に更新しました。")
                    
                except Exception as e:
                    print(f"    エラー (スラッグ: {cat['slug']}): {e}")
                    db.rollback()
                
                # サーバー負荷軽減のためわずかに待機
                await asyncio.sleep(1)

            print("\nBDSカテゴリー同期が完了しました。")
        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect_bds_categories())