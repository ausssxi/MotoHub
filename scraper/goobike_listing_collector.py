import asyncio
import os
import datetime
import re
import json
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Numeric, Integer, Boolean, Text, JSON, DateTime, ForeignKey, select
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# .envファイルを読み込む
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

# 出品情報
class Listing(Base):
    __tablename__ = "listings"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    bike_model_id = Column(BigInteger, nullable=True)
    shop_id = Column(BigInteger, nullable=True)
    title = Column(String(255), nullable=True) # タイトル追加
    source_platform = Column(String(50))
    source_url = Column(Text, nullable=False)
    price = Column(Numeric(12, 0))
    total_price = Column(Numeric(12, 0), nullable=True)
    model_year = Column(Integer, nullable=True)
    mileage = Column(Integer, nullable=True)
    image_urls = Column(JSON, nullable=True)
    is_sold_out = Column(Boolean, default=False)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

# 車種認識番号用
class BikeModelIdentifier(Base):
    __tablename__ = "bike_model_identifiers"
    id = Column(BigInteger, primary_key=True)
    bike_model_id = Column(BigInteger, nullable=False)
    site_id = Column(BigInteger, nullable=False)
    identifier = Column(String(100), nullable=False)

# サイトマスタ
class Site(Base):
    __tablename__ = "sites"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(50))

# 販売店マスタ
class Shop(Base):
    __tablename__ = "shops"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(255))
    address = Column(Text)

async def scrape_goobike():
    db = SessionLocal()
    
    # サイトID取得
    site = db.query(Site).filter(Site.name == "GooBike").first()
    if not site:
        print("エラー: sitesテーブルに 'GooBike' が見つかりません。")
        return
    site_id = site.id

    async with async_playwright() as p:
        print("ブラウザを起動しています...")
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
            
            maker_urls = []
            for link in maker_links:
                href = await link.get_attribute("href")
                if href:
                    maker_urls.append(base_url + href if href.startswith('/') else href)

            for m_url in maker_urls:
                print(f"\n--- メーカーページ: {m_url} ---")
                await page.goto(m_url, wait_until="domcontentloaded")
                
                bike_list_items = await page.query_selector_all("li.bike_list")
                
                for item in bike_list_items:
                    input_elem = await item.query_selector("input[name='model']")
                    identifier = await input_elem.get_attribute("value") if input_elem else None
                    link_elem = await item.query_selector("a")
                    model_page_path = await link_elem.get_attribute("href") if link_elem else None

                    if not identifier or not model_page_path:
                        continue

                    ident_record = db.query(BikeModelIdentifier).filter(
                        BikeModelIdentifier.site_id == site_id,
                        BikeModelIdentifier.identifier == identifier
                    ).first()

                    if not ident_record:
                        continue

                    bike_model_id = ident_record.bike_model_id
                    model_page_url = base_url + model_page_path if model_page_path.startswith('/') else model_page_path

                    print(f"  車種ID:{bike_model_id} の車両情報を取得中...")
                    model_page = await context.new_page()
                    try:
                        await model_page.goto(model_page_url, wait_until="domcontentloaded", timeout=60000)
                        vehicle_elements = await model_page.query_selector_all(".bike_sec")
                        
                        for v_el in vehicle_elements:
                            try:
                                # URLとタイトル取得
                                v_link_el = await v_el.query_selector("h4 span a")
                                if not v_link_el: continue
                                
                                v_path = await v_link_el.get_attribute("href")
                                v_url = base_url + v_path if v_path.startswith('/') else v_path
                                v_title = (await v_link_el.inner_text()).strip() # タイトル取得

                                # 価格解析
                                price_val = 0
                                price_td = await v_el.query_selector("td.num_td")
                                if price_td:
                                    p_text = await price_td.inner_text()
                                    p_match = re.search(r'(\d+\.?\d*)', p_text.replace(',', ''))
                                    if p_match: price_val = int(float(p_match.group(1)) * 10000)

                                total_price_val = None
                                total_span = await v_el.query_selector("span.total")
                                if total_span:
                                    t_text = await total_span.inner_text()
                                    t_match = re.search(r'(\d+\.?\d*)', t_text.replace(',', ''))
                                    if t_match: total_price_val = int(float(t_match.group(1)) * 10000)

                                # 年式・距離
                                year, mile = None, None
                                spec_lis = await v_el.query_selector_all(".cont01 ul li")
                                for li in spec_lis:
                                    li_text = await li.inner_text()
                                    if "モデル年式" in li_text:
                                        y_m = re.search(r'(\d{4})', li_text)
                                        if y_m: year = int(y_m.group(1))
                                    elif "走行距離" in li_text:
                                        m_m = re.search(r'(\d+,?\d*)', li_text.replace('Km', '').replace('km', ''))
                                        if m_m: mile = int(m_m.group(1).replace(',', ''))

                                # 画像
                                img_elem = await v_el.query_selector(".bike_img img")
                                images = []
                                if img_elem:
                                    img_url = await img_elem.get_attribute("real-url") or await img_elem.get_attribute("src")
                                    if img_url:
                                        images.append(base_url + img_url if img_url.startswith('/') else img_url)

                                # 販売店特定
                                shop_name_el = await v_el.query_selector(".shop_name a")
                                shop_id = None
                                if shop_name_el:
                                    shop_name = (await shop_name_el.inner_text()).strip()
                                    shop_record = db.query(Shop).filter(Shop.name == shop_name).first()
                                    if shop_record:
                                        shop_id = shop_record.id

                                # --- 保存処理 ---
                                existing = db.query(Listing).filter(Listing.source_url == v_url).first()
                                if not existing:
                                    new_listing = Listing(
                                        bike_model_id=bike_model_id,
                                        shop_id=shop_id, # nullableなのでNoneでもOK
                                        title=v_title,   # タイトル保存
                                        source_platform="GooBike",
                                        source_url=v_url,
                                        price=price_val,
                                        total_price=total_price_val,
                                        model_year=year,
                                        mileage=mile,
                                        image_urls=images,
                                        is_sold_out=False
                                    )
                                    db.add(new_listing)
                                else:
                                    existing.title = v_title
                                    existing.price = price_val
                                    existing.total_price = total_price_val
                                    existing.updated_at = datetime.datetime.now()
                                
                                db.commit()

                            except Exception as e:
                                # 個別の保存エラー時はロールバックして次へ
                                db.rollback()
                                print(f"    車両解析/保存エラー: {e}")
                        
                        await asyncio.sleep(1)
                    except Exception as e:
                        print(f"  車種ページ取得エラー: {e}")
                    finally:
                        await model_page.close()

        except Exception as e:
            print(f"致命的なエラー: {e}")
        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(scrape_goobike())