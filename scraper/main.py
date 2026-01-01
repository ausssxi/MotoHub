import asyncio
import os
import datetime
import re
import json
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Numeric, Integer, Boolean, Text, JSON, DateTime
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

async def scrape_goobike():
    async with async_playwright() as p:
        print("ブラウザを起動しています...")
        browser = await p.chromium.launch(headless=True)
        
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            viewport={'width': 1280, 'height': 1200}
        )
        page = await context.new_page()

        # ホンダの検索結果一覧
        target_url = "https://www.goobike.com/cgi-bin/search/search_result.cgi?maker=1"
        print(f"アクセス中: {target_url}")
        
        try:
            await page.goto(target_url, wait_until="domcontentloaded", timeout=60000)
            # 画像の遅延読み込みなどを考慮して少し待機
            await asyncio.sleep(3)

            # 車両ブロックの取得
            bike_elements = await page.query_selector_all(".bike_sec")
            
            if not bike_elements:
                print("車両要素(.bike_sec)が見つかりませんでした。")
                return

            print(f"{len(bike_elements)} 件の要素を発見しました。解析を開始します。")

            results = []
            for i, element in enumerate(bike_elements):
                try:
                    # 1. タイトルとURL
                    title_elem = await element.query_selector("h4 span a")
                    if not title_elem:
                        continue
                    
                    title = await title_elem.inner_text()
                    path = await title_elem.get_attribute("href")
                    url = f"https://www.goobike.com{path}" if path.startswith('/') else path

                    # 2. 価格 (車両価格と支払総額)
                    # 車両価格
                    price_val = 0
                    price_td = await element.query_selector("td.num_td")
                    if price_td:
                        price_text = await price_td.inner_text()
                        match = re.search(r'(\d+\.?\d*)', price_text.replace(',', ''))
                        if match:
                            price_val = int(float(match.group(1)) * 10000)

                    # 支払総額
                    total_price_val = None
                    total_span = await element.query_selector("span.total")
                    if total_span:
                        total_text = await total_span.inner_text()
                        t_match = re.search(r'(\d+\.?\d*)', total_text.replace(',', ''))
                        if t_match:
                            total_price_val = int(float(t_match.group(1)) * 10000)

                    # 3. スペック (年式・走行距離)
                    year = None
                    mile = None
                    spec_lis = await element.query_selector_all(".cont01 ul li")
                    for li in spec_lis:
                        li_text = await li.inner_text()
                        if "モデル年式" in li_text:
                            y_match = re.search(r'(\d{4})', li_text)
                            if y_match: year = int(y_match.group(1))
                        elif "走行距離" in li_text:
                            # "14Km" や "1,500km" に対応
                            m_match = re.search(r'(\d+,?\d*)', li_text.replace('Km', '').replace('km', ''))
                            if m_match: mile = int(m_match.group(1).replace(',', ''))

                    # 4. 画像URL (real-url属性を優先)
                    img_elem = await element.query_selector(".bike_img img")
                    images = []
                    if img_elem:
                        # GooBikeは遅延読み込みのためreal-urlに真のパスがある
                        img_url = await img_elem.get_attribute("real-url") or await img_elem.get_attribute("src")
                        if img_url:
                            # 相対パスなら補完
                            if img_url.startswith('/'):
                                img_url = f"https://www.goobike.com{img_url}"
                            images.append(img_url)

                    results.append({
                        "source_platform": "GooBike",
                        "source_url": url,
                        "price": price_val,
                        "total_price": total_price_val,
                        "model_year": year,
                        "mileage": mile,
                        "image_urls": images
                    })
                    print(f"[{i}] 解析成功: {title.strip()[:20]}... | 価格: {price_val}円 | 年式: {year} | 距離: {mile}")

                except Exception as e:
                    print(f"[{i}] 要素解析エラー: {e}")

            # 5. DB保存
            if results:
                db = SessionLocal()
                try:
                    added_count = 0
                    for item in results:
                        # URLで重複チェック
                        existing = db.query(Listing).filter(Listing.source_url == item["source_url"]).first()
                        if not existing:
                            db.add(Listing(**item))
                            added_count += 1
                        else:
                            # 既存データがある場合は価格などを更新する処理を入れても良い
                            existing.price = item["price"]
                            existing.total_price = item["total_price"]
                            existing.updated_at = datetime.datetime.now()
                            
                    db.commit()
                    print(f"--- DB処理完了 ---")
                    print(f"新規登録: {added_count} 件")
                    print(f"総解析数: {len(results)} 件")
                except Exception as e:
                    print(f"DBエラー: {e}")
                    db.rollback()
                finally:
                    db.close()
            else:
                print("解析に成功したデータがありませんでした。")
            
        except Exception as e:
            print(f"アクセスエラー: {e}")
        finally:
            await browser.close()

if __name__ == "__main__":
    asyncio.run(scrape_goobike())