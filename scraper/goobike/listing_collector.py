import asyncio
import os
import datetime
import re
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Numeric, Integer, Boolean, Text, JSON, DateTime, or_
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
    site = db.query(Site).filter(Site.name == "GooBike").first()
    if not site:
        print("エラー: sitesテーブルに 'GooBike' が見つかりません。")
        return
    site_id = site.id

    async with async_playwright() as p:
        print("GooBike出品情報コレクターを起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        page = await context.new_page()
        base_url = "https://www.goobike.com"
        
        try:
            print("メーカー一覧を取得中...")
            await page.goto(f"{base_url}/maker-top/index.html", wait_until="domcontentloaded")
            maker_links = await page.query_selector_all(".makerlist .mj a")
            maker_urls = [base_url + (await link.get_attribute("href")) for link in maker_links]

            for m_url in maker_urls:
                await page.goto(m_url, wait_until="domcontentloaded")
                bike_list_items = await page.query_selector_all("li.bike_list")
                
                for item in bike_list_items:
                    input_elem = await item.query_selector("input[name='model']")
                    identifier = await input_elem.get_attribute("value") if input_elem else None
                    link_elem = await item.query_selector("a")
                    model_path = await link_elem.get_attribute("href") if link_elem else None

                    if not identifier or not model_path: continue

                    ident_record = db.query(BikeModelIdentifier).filter(
                        BikeModelIdentifier.site_id == site_id,
                        BikeModelIdentifier.identifier == identifier
                    ).first()

                    if not ident_record: continue
                    bike_model_id = ident_record.bike_model_id
                    
                    model_page = await context.new_page()
                    try:
                        await model_page.goto(base_url + model_path, wait_until="domcontentloaded", timeout=60000)
                        vehicle_elements = await model_page.query_selector_all(".bike_sec")
                        
                        for v_el in vehicle_elements:
                            try:
                                v_link_el = await v_el.query_selector("h4 span a")
                                if not v_link_el: continue
                                v_url = base_url + (await v_link_el.get_attribute("href"))
                                v_title = (await v_link_el.inner_text()).strip()

                                # 販売店特定ロジック (識別番号優先)
                                shop_id = None
                                shop_name_el = await v_el.query_selector(".shop_name a")
                                if shop_name_el:
                                    shop_href = await shop_name_el.get_attribute("href")
                                    if shop_href:
                                        s_match = re.search(r'client_(\d+)', shop_href)
                                        if s_match:
                                            s_ident = s_match.group(1)
                                            sid_rec = db.query(ShopIdentifier).filter(ShopIdentifier.site_id == site_id, ShopIdentifier.identifier == s_ident).first()
                                            if sid_rec: shop_id = sid_rec.shop_id

                                # 出品情報の保存
                                existing = db.query(Listing).filter(Listing.source_url == v_url).first()
                                if not existing:
                                    new_listing = Listing(bike_model_id=bike_model_id, shop_id=shop_id, site_id=site_id, title=v_title, source_url=v_url, is_sold_out=False)
                                    db.add(new_listing)
                                else:
                                    existing.shop_id = shop_id
                                    existing.updated_at = datetime.datetime.now()
                                db.commit()
                            except Exception:
                                db.rollback()
                    finally:
                        await model_page.close()
        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())