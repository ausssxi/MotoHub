import asyncio
import os
import datetime
import re
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Text, DateTime, ForeignKey, UniqueConstraint, or_
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

class Site(Base):
    __tablename__ = "sites"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(50), unique=True)

class Shop(Base):
    __tablename__ = "shops"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    name = Column(String(255), nullable=False)
    prefecture = Column(String(20), nullable=True)
    address = Column(Text, nullable=True)
    phone = Column(String(20), nullable=True)
    website_url = Column(Text, nullable=True)

class ShopIdentifier(Base):
    __tablename__ = "shop_identifiers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    shop_id = Column(BigInteger, ForeignKey("shops.id", ondelete="CASCADE"), nullable=False)
    site_id = Column(BigInteger, ForeignKey("sites.id", ondelete="CASCADE"), nullable=False)
    identifier = Column(String(100), nullable=False)
    
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_shop_site_identifier_uc'),)


async def collect():
    async with async_playwright() as p:
        print("BDSショップコレクターを起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            viewport={'width': 1920, 'height': 1080}
        )
        page = await context.new_page()
        base_url = "https://www.bds-bikesensor.net"
        db = SessionLocal()
        
        bds_site = db.query(Site).filter(Site.name == "BDS").first()
        if not bds_site:
            db.close()
            await browser.close()
            return
        site_id = bds_site.id

        pref_map = {"01": "北海道", "13": "東京", "23": "愛知", "27": "大阪", "40": "福岡"} # 省略

        try:
            for code, pref_name in pref_map.items():
                current_url = f"{base_url}/shop?prefectureCodes%5B%5D={code}"
                print(f"\n--- 都道府県: {pref_name} を収集開始 ---")
                
                page_num = 1
                while current_url:
                    await page.goto(current_url, wait_until="domcontentloaded", timeout=60000)
                    shop_items = await page.query_selector_all("li.c-search_block_list_item.type_shop")
                    
                    if not shop_items: break

                    for item in shop_items:
                        try:
                            name_el = await item.query_selector(".c-search_block_shop_title01 a")
                            if not name_el: continue
                            name = (await name_el.inner_text()).strip()
                            href = await name_el.get_attribute("href")
                            
                            identifier = re.search(r'client/(\d+)', href).group(1) if href else None

                            # 詳細情報のパース
                            # ... 省略 ...

                            existing_shop = db.query(Shop).filter(Shop.name == name).first()
                            if not existing_shop:
                                existing_shop = Shop(name=name, prefecture=pref_name)
                                db.add(existing_shop)
                                db.flush()
                            
                            if identifier:
                                existing_ident = db.query(ShopIdentifier).filter(
                                    ShopIdentifier.site_id == site_id,
                                    ShopIdentifier.identifier == identifier
                                ).first()
                                if not existing_ident:
                                    db.add(ShopIdentifier(shop_id=existing_shop.id, site_id=site_id, identifier=identifier))

                            db.commit()
                        except Exception:
                            db.rollback()

                    next_btn = await page.query_selector("div.c-pager a.c-btn_next")
                    current_url = base_url + (await next_btn.get_attribute("href")) if next_btn else None
                    page_num += 1
                    await asyncio.sleep(1)

        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())