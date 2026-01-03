import asyncio
import os
import datetime
import re
import json
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Numeric, Integer, Boolean, Text, JSON, DateTime, ForeignKey, select, or_
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

class Listing(Base):
    __tablename__ = "listings"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    bike_model_id = Column(BigInteger, nullable=True)
    shop_id = Column(BigInteger, nullable=True)
    site_id = Column(BigInteger, nullable=False)
    title = Column(String(255), nullable=True)
    source_url = Column(Text, nullable=False)
    price = Column(Numeric(12, 0))
    total_price = Column(Numeric(12, 0), nullable=True)
    model_year = Column(Integer, nullable=True)
    mileage = Column(Integer, nullable=True)
    image_urls = Column(JSON, nullable=True)
    is_sold_out = Column(Boolean, default=False)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

class BikeModelIdentifier(Base):
    __tablename__ = "bike_model_identifiers"
    id = Column(BigInteger, primary_key=True)
    bike_model_id = Column(BigInteger, nullable=False)
    site_id = Column(BigInteger, nullable=False)
    identifier = Column(String(100), nullable=False)

class ShopIdentifier(Base):
    __tablename__ = "shop_identifiers"
    id = Column(BigInteger, primary_key=True)
    shop_id = Column(BigInteger, nullable=False)
    site_id = Column(BigInteger, nullable=False)
    identifier = Column(String(100), nullable=False)

class Site(Base):
    __tablename__ = "sites"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(50))

class Shop(Base):
    __tablename__ = "shops"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(255))
    address = Column(Text)


async def collect():
    db = SessionLocal()
    site = db.query(Site).filter(Site.name == "BDS").first()
    if not site:
        print("エラー: sitesテーブルに 'BDS' が見つかりません。")
        return
    site_id = site.id

    async with async_playwright() as p:
        print("BDSリスティングコレクターを起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            viewport={'width': 1920, 'height': 1080}
        )
        page = await context.new_page()
        base_url = "https://www.bds-bikesensor.net"
        
        # 実際には全件取得するように調整
        maker_list = [{"slug": "honda", "name": "ホンダ"}] 

        try:
            for m in maker_list:
                m_url = f"{base_url}/bike/maker/{m['slug']}"
                print(f"\n--- {m['name']} の出品情報を取得開始 ---")
                
                await page.goto(m_url, wait_until="domcontentloaded", timeout=60000)
                model_items = await page.query_selector_all(".model_item")
                
                model_links = []
                for item in model_items:
                    m_input = await item.query_selector("input.model-checkbox")
                    identifier = await m_input.get_attribute("value") if m_input else None
                    m_link = await item.query_selector("a.c-bike_image")
                    href = await m_link.get_attribute("href") if m_link else None
                    
                    if identifier and href:
                        ident_record = db.query(BikeModelIdentifier).filter(
                            BikeModelIdentifier.site_id == site_id,
                            BikeModelIdentifier.identifier == identifier
                        ).first()
                        
                        if ident_record:
                            model_links.append({
                                "id": ident_record.bike_model_id,
                                "url": base_url + href if href.startswith('/') else href
                            })

                for m_info in model_links:
                    target_model_id = m_info["id"]
                    current_search_url = m_info["url"]
                    
                    while current_search_url:
                        await page.goto(current_search_url, wait_until="networkidle", timeout=60000)
                        bike_blocks = await page.query_selector_all("li.type_bike, li.type_bike_sp")

                        for bike in bike_blocks:
                            try:
                                title_el = await bike.query_selector(".c-search_block_title a, .c-search_block_title02 a")
                                if not title_el: continue
                                v_title = (await title_el.inner_text()).strip()
                                v_url = base_url + (await title_el.get_attribute("href"))

                                # 価格・スペック等の解析 (詳細は既存ロジックを継承)
                                # ... 省略 ...

                                # 販売店特定ロジック
                                shop_id = None
                                shop_name_el = await bike.query_selector(".c-search_block_bottom_title01")
                                shop_detail_link_el = await bike.query_selector(".c-search_block_bottom_lead a")

                                if shop_name_el:
                                    shop_href = await shop_detail_link_el.get_attribute("href") if shop_detail_link_el else None
                                    if shop_href:
                                        id_match = re.search(r'client/(\d+)', shop_href)
                                        if id_match:
                                            shop_identifier = id_match.group(1)
                                            sid_record = db.query(ShopIdentifier).filter(
                                                ShopIdentifier.site_id == site_id,
                                                ShopIdentifier.identifier == shop_identifier
                                            ).first()
                                            if sid_record:
                                                shop_id = sid_record.shop_id

                                # 保存処理
                                # ... 省略 ...
                                db.commit()

                            except Exception as e:
                                db.rollback()

                        next_btn = await page.query_selector(".c-pager_next a")
                        current_search_url = base_url + (await next_btn.get_attribute("href")) if next_btn else None
                        await asyncio.sleep(1)

        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())