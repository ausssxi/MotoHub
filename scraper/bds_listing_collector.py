import asyncio
import os
import datetime
import re
import json
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Numeric, Integer, Boolean, Text, JSON, DateTime, ForeignKey, select, or_
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

class Listing(Base):
    __tablename__ = "listings"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    bike_model_id = Column(BigInteger, nullable=True)
    shop_id = Column(BigInteger, nullable=True)
    title = Column(String(255), nullable=True)
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

async def scrape_bds_listings():
    db = SessionLocal()
    
    # BDSのサイトIDを取得
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
        
        # メーカーリスト (省略版 - 実際には全件入れてください)
        maker_list = [{"slug": "honda", "name": "ホンダ"}] 

        try:
            for m in maker_list:
                m_url = f"{base_url}/bike/maker/{m['slug']}"
                print(f"\n--- {m['name']} の出品情報を取得開始 ---")
                
                try:
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
                            
                            try:
                                await page.wait_for_selector("li.type_bike, li.type_bike_sp", timeout=5000)
                            except:
                                break

                            bike_blocks = await page.query_selector_all("li.type_bike, li.type_bike_sp")
                            print(f"  車種ID:{target_model_id} - {len(bike_blocks)}台を解析中...")

                            for bike in bike_blocks:
                                try:
                                    # タイトルとURL
                                    title_el = await bike.query_selector(".c-search_block_title a, .c-search_block_title02 a")
                                    if not title_el: continue
                                    v_title = (await title_el.inner_text()).strip()
                                    v_url = base_url + (await title_el.get_attribute("href"))

                                    # 価格取得
                                    price_val, total_price_val = 0, None
                                    price_items = await bike.query_selector_all(".c-search_block_price, .c-search_block_info_wrap .c-search_block_price")
                                    for p_item in price_items:
                                        label = await p_item.query_selector(".c-search_block_price_title")
                                        value = await p_item.query_selector(".c-search_block_price_text")
                                        if label and value:
                                            l_text = await label.inner_text()
                                            v_text = (await value.inner_text()).replace(',', '').replace('\n', '').strip()
                                            match = re.search(r'(\d+\.?\d*)', v_text)
                                            if match:
                                                num = int(float(match.group(1)) * 10000)
                                                if "本体価格" in l_text: price_val = num
                                                elif "支払総額" in l_text: total_price_val = num

                                    # スペック取得 (モデル年・距離)
                                    year, mile = None, None
                                    status_cols = await bike.query_selector_all(".c-search_status_col")
                                    for col in status_cols:
                                        h_el = await col.query_selector(".c-search_status_head")
                                        v_el = await col.query_selector(".c-search_status_title01")
                                        if h_el and v_el:
                                            h_txt = await h_el.inner_text()
                                            v_txt = await v_el.inner_text()
                                            if "モデル年" in h_txt and "不明" not in v_txt:
                                                y_m = re.search(r'(\d{4})', v_txt)
                                                if y_m: year = int(y_m.group(1))
                                            elif "距離" in h_txt:
                                                m_m = re.search(r'(\d+)', v_txt.replace(',', ''))
                                                if m_m: mile = int(m_m.group(1))

                                    # 画像
                                    img_el = await bike.query_selector(".c-bike_image figure, .c-bike_image img")
                                    images = []
                                    if img_el:
                                        img_src = await img_el.get_attribute("data-src") or await img_el.get_attribute("src")
                                        if img_src and "blank" not in img_src: images.append(img_src)

                                    # --- 重要：販売店紐付け (認識番号 identifier を使用) ---
                                    shop_id = None
                                    # 1. 販売店ページへのリンクからIDを抽出
                                    shop_link_el = await bike.query_selector(".c-search_block_bottom_title01 a, .c-search_block_title01 a")
                                    if shop_link_el:
                                        shop_href = await shop_link_el.get_attribute("href")
                                        # URL例: /shop/client/60534
                                        id_match = re.search(r'client/(\d+)', shop_href)
                                        if id_match:
                                            shop_identifier = id_match.group(1)
                                            
                                            # 2. shop_identifiersテーブルを検索
                                            shop_ident_record = db.query(ShopIdentifier).filter(
                                                ShopIdentifier.site_id == site_id,
                                                ShopIdentifier.identifier == shop_identifier
                                            ).first()
                                            
                                            if shop_ident_record:
                                                shop_id = shop_ident_record.shop_id
                                            else:
                                                # もしIDで見つからない場合は名前+住所で最終チェック
                                                shop_name = (await shop_link_el.inner_text()).strip()
                                                shop_record = db.query(Shop).filter(Shop.name == shop_name).first()
                                                if shop_record:
                                                    shop_id = shop_record.id

                                    # 保存
                                    existing = db.query(Listing).filter(Listing.source_url == v_url).first()
                                    if not existing:
                                        new_listing = Listing(
                                            bike_model_id=target_model_id,
                                            shop_id=shop_id,
                                            title=v_title,
                                            source_platform="BDS",
                                            source_url=v_url,
                                            price=price_val,
                                            total_price=total_price_val,
                                            model_year=year,
                                            mileage=mile,
                                            image_urls=images,
                                            is_sold_out=False
                                        )
                                        db.add(new_listing)
                                        print(f"      [登録] {v_title} (ShopID:{shop_id})")
                                    else:
                                        existing.shop_id = shop_id
                                        existing.price = price_val
                                        existing.total_price = total_price_val
                                        db.commit()
                                    
                                    db.commit()

                                except Exception as e:
                                    db.rollback()
                                    print(f"      車両個別解析エラー: {e}")

                            # ページネーション
                            next_btn = await page.query_selector(".c-pager_next a")
                            if next_btn:
                                current_search_url = base_url + (await next_btn.get_attribute("href"))
                                await asyncio.sleep(1)
                            else:
                                current_search_url = None

                except Exception as e:
                    print(f"  車種巡回エラー: {e}")

            print("\nBDS出品情報の全件収集が完了しました。")

        except Exception as e:
            print(f"致命的なエラー: {e}")
        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(scrape_bds_listings())