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

async def collect():
    async with async_playwright() as p:
        print("GooBikeカテゴリー同期を開始します...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        page = await context.new_page()
        base_url = "https://www.goobike.com"
        db = SessionLocal()

        try:
            for i in range(1, 17):
                genre_id = str(i).zfill(2)
                genre_url = f"{base_url}/genre-{genre_id}/index.html"
                print(f"  解析中: {genre_url}")
                
                try:
                    await page.goto(genre_url, wait_until="domcontentloaded", timeout=60000)
                    style_elem = await page.query_selector("li strong")
                    if not style_elem: continue
                    style_name = (await style_elem.inner_text()).strip()
                    
                    bike_elements = await page.query_selector_all("li.bike_list em b")
                    for bike_elem in bike_elements:
                        raw_name = await bike_elem.inner_text()
                        model_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', raw_name).strip()
                        if not model_name: continue
                        
                        targets = db.query(BikeModel).filter(
                            BikeModel.name == model_name,
                            or_(BikeModel.category == None, BikeModel.category == "不明")
                        ).all()
                        
                        for t in targets:
                            t.category = style_name
                    db.commit()
                except Exception as e:
                    print(f"    エラー (ジャンル {genre_id}): {e}")
                    db.rollback()

            print("\nGooBikeカテゴリー同期が完了しました。")
        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())