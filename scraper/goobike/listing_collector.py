import asyncio
import os
import datetime
import re
import random
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

# 同時接続数を制限
MAX_CONCURRENT_PAGES = 3
semaphore = asyncio.Semaphore(MAX_CONCURRENT_PAGES)

async def block_resources(route):
    """画像、CSS、フォントなどの不要なリソースを遮断"""
    if route.request.resource_type in ["image", "media", "font", "stylesheet"]:
        await route.abort()
    else:
        await route.continue_()

async def process_model_page(context, base_url, model_path, bike_model_id, site_id, shop_cache, known_urls):
    """車種ごとの出品一覧ページを解析。既知のURLはスキップする。"""
    async with semaphore:
        db = SessionLocal()
        page = await context.new_page()
        await page.route("**/*", block_resources)
        
        try:
            target_url = base_url + model_path
            await page.goto(target_url, wait_until="domcontentloaded", timeout=60000)
            
            vehicle_elements = await page.query_selector_all(".bike_sec")
            new_records = 0
            
            for v_el in vehicle_elements:
                try:
                    # まずURLだけを取得してチェック
                    v_link_el = await v_el.query_selector("h4 span a")
                    if not v_link_el: continue
                    v_url = base_url + (await v_link_el.get_attribute("href"))

                    # --- 重複スキップ処理 ---
                    if v_url in known_urls:
                        continue

                    # ここから下は「新しいURL」の場合のみ実行される
                    v_title = (await v_link_el.inner_text()).strip()

                    # 価格の抽出
                    price_val, total_price_val = 0, None
                    price_td = await v_el.query_selector("td.num_td")
                    if price_td:
                        p_text = await price_td.inner_text()
                        p_match = re.search(r'(\d+\.?\d*)', p_text.replace(',', ''))
                        if p_match: price_val = int(float(p_match.group(1)) * 10000)

                    total_span = await v_el.query_selector("span.total")
                    if total_span:
                        t_text = await total_span.inner_text()
                        t_match = re.search(r'(\d+\.?\d*)', t_text.replace(',', ''))
                        if t_match: total_price_val = int(float(t_match.group(1)) * 10000)

                    # --- 追加: 年式・走行距離の抽出 ---
                    year, mile = None, None
                    spec_lis = await v_el.query_selector_all(".cont01 ul li")
                    for li in spec_lis:
                        li_text = await li.inner_text()
                        if "年式" in li_text:
                            # 例: 「初年度登録：2022年」
                            y_m = re.search(r'(\d{4})', li_text)
                            if y_m: year = int(y_m.group(1))
                        elif "走行" in li_text:
                            # 例: 「走行距離：1,234Km」
                            m_m = re.search(r'(\d+,?\d*)', li_text.replace('Km', '').replace('km', ''))
                            if m_m: mile = int(m_m.group(1).replace(',', ''))

                    # 画像
                    img_elem = await v_el.query_selector(".bike_img img")
                    images = []
                    if img_elem:
                        img_url = await img_elem.get_attribute("real-url") or await img_elem.get_attribute("src")
                        if img_url: images.append(base_url + img_url if img_url.startswith('/') else img_url)

                    # 販売店特定
                    shop_id = None
                    shop_name_el = await v_el.query_selector(".shop_name a")
                    if shop_name_el:
                        shop_href = await shop_name_el.get_attribute("href")
                        if shop_href:
                            s_match = re.search(r'client_(\d+)', shop_href)
                            if s_match:
                                shop_id = shop_cache.get(s_match.group(1))

                    # 新規登録
                    new_listing = Listing(
                        bike_model_id=bike_model_id,
                        shop_id=shop_id,
                        site_id=site_id,
                        title=v_title,
                        source_url=v_url,
                        price=price_val,
                        total_price=total_price_val,
                        model_year=year,
                        mileage=mile,
                        image_urls=images,
                        is_sold_out=False
                    )
                    db.add(new_listing)
                    db.commit()
                    known_urls.add(v_url)
                    new_records += 1
                    
                except Exception:
                    db.rollback()
            
            if new_records > 0:
                print(f"  [完了] {model_path}: {new_records}件の新着車両を登録")
                        
        except Exception as e:
            print(f"  [エラー] ページ取得失敗 ({model_path}): {e}")
        finally:
            db.close()
            await page.close()

async def collect():
    db = SessionLocal()
    site = db.query(Site).filter(Site.name == "GooBike").first()
    if not site:
        print("エラー: sitesテーブルに 'GooBike' が見つかりません。")
        return
    site_id = site.id

    print("キャッシュを構築中...")
    model_ident_cache = {i.identifier: i.bike_model_id for i in db.query(BikeModelIdentifier).filter(BikeModelIdentifier.site_id == site_id).all()}
    shop_cache = {i.identifier: i.shop_id for i in db.query(ShopIdentifier).filter(ShopIdentifier.site_id == site_id).all()}
    
    # URLキャッシュの構築
    known_urls = {l.source_url for l in db.query(Listing.source_url).all()}
    print(f"既知のURLを {len(known_urls)} 件ロードしました。")
    
    db.close()

    async with async_playwright() as p:
        print("GooBike出品情報コレクターを起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        
        base_url = "https://www.goobike.com"
        
        try:
            main_page = await context.new_page()
            await main_page.route("**/*", block_resources)
            await main_page.goto(f"{base_url}/maker-top/index.html", wait_until="domcontentloaded")
            maker_links = await main_page.query_selector_all(".makerlist .mj a")
            maker_urls = [base_url + (await link.get_attribute("href")) for link in maker_links]
            await main_page.close()

            # 各メーカーの車種をスキャン
            for m_url in maker_urls:
                temp_page = await context.new_page()
                await temp_page.route("**/*", block_resources)
                await temp_page.goto(m_url, wait_until="domcontentloaded")
                
                bike_list_items = await temp_page.query_selector_all("li.bike_list")
                process_tasks = []
                
                for item in bike_list_items:
                    input_elem = await item.query_selector("input[name='model']")
                    identifier = await input_elem.get_attribute("value") if input_elem else None
                    link_elem = await item.query_selector("a")
                    model_path = await link_elem.get_attribute("href") if link_elem else None

                    if identifier and model_path:
                        bike_model_id = model_ident_cache.get(identifier)
                        if bike_model_id:
                            process_tasks.append(
                                process_model_page(context, base_url, model_path, bike_model_id, site_id, shop_cache, known_urls)
                            )
                
                # メーカーごとに並列実行
                if process_tasks:
                    await asyncio.gather(*process_tasks)
                
                await temp_page.close()
                await asyncio.sleep(1)

            print("\nすべての出品情報の同期が完了しました。")
        finally:
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())