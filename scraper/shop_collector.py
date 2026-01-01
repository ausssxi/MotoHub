import asyncio
import os
import datetime
import re
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Text, DateTime
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

# 販売店テーブル定義
class Shop(Base):
    __tablename__ = "shops"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    name = Column(String(255), nullable=False)
    prefecture = Column(String(20), nullable=True)
    address = Column(Text, nullable=True)
    phone = Column(String(20), nullable=True)
    website_url = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

async def collect_shops():
    async with async_playwright() as p:
        print("ブラウザを起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        page = await context.new_page()

        base_url = "https://www.goobike.com"
        shop_top_url = f"{base_url}/shop/"
        
        try:
            print(f"販売店TOPにアクセス中: {shop_top_url}")
            await page.goto(shop_top_url, wait_until="domcontentloaded", timeout=60000)
            
            # 1. 各都道府県のリンクを取得
            pref_links = await page.query_selector_all(".mapBox li a")
            pref_urls = []
            for link in pref_links:
                href = await link.get_attribute("href")
                pref_name = await link.inner_text()
                if href:
                    pref_urls.append({
                        "name": pref_name,
                        "url": base_url + href if href.startswith('/') else href
                    })

            print(f"{len(pref_urls)} 都道府県のリンクを取得しました。")

            db = SessionLocal()

            # 2. 各都道府県のリストページを巡回（詳細ページには飛ばない）
            for pref in pref_urls:
                print(f"\n--- {pref['name']} の販売店を解析中 ---")
                await page.goto(pref['url'], wait_until="domcontentloaded", timeout=60000)
                
                # 販売店ブロックを取得
                # リストページにおいて、各店舗は通常 .shop_header と .shop_address を持っています
                shop_elements = await page.query_selector_all(".shop_header")
                print(f"このページで {len(shop_elements)} 店発見しました。")

                for shop_el in shop_elements:
                    try:
                        # 店名取得
                        name_el = await shop_el.query_selector(".shop_name")
                        name = (await name_el.inner_text()).strip() if name_el else ""
                        
                        if not name:
                            continue

                        # 住所取得（.shop_header の親要素または隣接要素から .shop_address を探す）
                        # evaluate を使い JavaScript で隣接要素から住所を取得
                        address = await page.evaluate(
                            "(el) => { const addr = el.parentElement.querySelector('.shop_address'); return addr ? addr.innerText : ''; }", 
                            shop_el
                        )
                        address = address.strip()

                        # 重複チェック (店名と住所)
                        existing = db.query(Shop).filter(
                            Shop.name == name, 
                            Shop.address == address
                        ).first()

                        if not existing:
                            new_shop = Shop(
                                name=name,
                                prefecture=pref['name'],
                                address=address
                            )
                            db.add(new_shop)
                            print(f"  [登録] {name}")
                        else:
                            # 既に存在する場合は都道府県情報などを補完更新（必要あれば）
                            if not existing.prefecture:
                                existing.prefecture = pref['name']
                            print(f"  [既知] {name}")
                        
                        # チャンクごとにコミット（1件ずつだと遅いため。ここでは小規模なので都度でもOK）
                        db.commit()

                    except Exception as e:
                        print(f"  店舗情報抽出エラー: {e}")

                # ページあたりの負荷軽減（リストページのみなので短めでOK）
                await asyncio.sleep(0.5)

            db.close()
            print("\nすべての販売店データの収集が完了しました。")

        except Exception as e:
            print(f"致命的エラー: {e}")
        finally:
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect_shops())