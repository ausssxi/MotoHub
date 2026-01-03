import asyncio
import os
import datetime
import re
import unicodedata
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, func
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# 環境変数の読み込み (フォルダが深くなったためパスを修正)
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
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    manufacturer_id = Column(BigInteger)
    name = Column(String(255), nullable=False, unique=True)
    displacement = Column(Integer, nullable=True)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

def robust_normalize(text):
    """文字のゆれを徹底的に排除する"""
    if not text:
        return ""
    text = unicodedata.normalize('NFKC', text)
    text = text.upper()
    text = re.sub(r'[ー－―—‐-]', '-', text)
    return text.strip()


async def collect():
    async with async_playwright() as p:
        print("BDS排気量コレクターを起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        page = await context.new_page()
        db = SessionLocal()

        maker_list_raw = [
            {"slug": "honda", "name": "ホンダ"}, {"slug": "suzuki", "name": "スズキ"},
            {"slug": "yamaha", "name": "ヤマハ"}, {"slug": "kawasaki", "name": "カワサキ"},
            {"slug": "bmw", "name": "BMW"}, {"slug": "ktm", "name": "KTM"},
            {"slug": "ducati", "name": "ドゥカティ"}, {"slug": "triumph", "name": "トライアンフ"},
            {"slug": "harley_davidson", "name": "ハーレーダビッドソン"}
            # 他のメーカーも必要に応じて追加
        ]

        try:
            for m in maker_list_raw:
                m_url = f"https://www.bds-bikesensor.net/bike/maker/{m['slug']}"
                print(f"\n--- {m['name']} の解析 ---")
                
                try:
                    await page.goto(m_url, wait_until="domcontentloaded", timeout=60000)
                    model_items = await page.query_selector_all(".model_item")
                except Exception:
                    continue
                
                for item in model_items:
                    m_link = await item.query_selector("a.c-bike_image")
                    if not m_link: continue
                    
                    raw_site_name = (await m_link.get_attribute("title") or "").strip()
                    normalized_site_name = robust_normalize(raw_site_name)
                    href = await m_link.get_attribute("href")
                    
                    if not normalized_site_name or not href: continue

                    model_record = db.query(BikeModel).filter(
                        or_(
                            BikeModel.name == raw_site_name,
                            func.upper(BikeModel.name) == normalized_site_name
                        )
                    ).first()

                    if model_record and model_record.displacement and model_record.displacement > 0:
                        continue

                    search_page_url = "https://www.bds-bikesensor.net" + (href if href.startswith('/') else '/' + href)
                    
                    sub_page = None
                    try:
                        sub_page = await context.new_page()
                        await sub_page.goto(search_page_url, wait_until="domcontentloaded", timeout=30000)
                        
                        status_cols = await sub_page.query_selector_all(".c-search_status_col")
                        disp_val = None
                        for col in status_cols:
                            head = await col.query_selector(".c-search_status_head")
                            if head and "排気量" in (await head.inner_text()):
                                val_el = await col.query_selector(".c-search_status_title01")
                                if val_el:
                                    m_digit = re.search(r'(\d+)', await val_el.inner_text())
                                    if m_digit: disp_val = int(m_digit.group(1))
                                    break
                        
                        if disp_val and model_record:
                            model_record.displacement = disp_val
                            db.commit()
                            print(f"    [更新] {model_record.name} -> {disp_val}cc")
                        
                        await sub_page.close()
                    except Exception:
                        if sub_page: await sub_page.close()

                await asyncio.sleep(0.5)

        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())