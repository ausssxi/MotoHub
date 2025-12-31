import asyncio
import os
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Decimal, Integer, Boolean, Text, JSON, DateTime
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker
import datetime
from dotenv import load_dotenv

# .envファイルを読み込む (プロジェクトルートの.envを探す)
load_dotenv()

# データベース接続設定
# パスワードなどの機密情報はコードに書かず、環境変数からのみ取得するようにします
user = os.getenv("DB_USERNAME")
password = os.getenv("DB_PASSWORD")
host = os.getenv("DB_HOST", "db")
port = os.getenv("DB_PORT", "3306")
database = os.getenv("DB_DATABASE")

# SQLAlchemy用の接続URLを構築
DATABASE_URL = f"mysql+pymysql://{user}:{password}@{host}:{port}/{database}"

if not all([user, password, database]):
    print("Warning: Database environment variables are not fully set. Check your .env file.")

engine = create_engine(DATABASE_URL)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
Base = declarative_base()

# 簡易的なモデル定義 (DB定義書に準拠)
class Listing(Base):
    __tablename__ = "listings"
    id = Column(BigInteger, primary_key=True, index=True)
    bike_model_id = Column(BigInteger)
    shop_id = Column(BigInteger)
    source_platform = Column(String(50))
    source_url = Column(Text, nullable=False)
    price = Column(Decimal(12, 0))
    total_price = Column(Decimal(12, 0))
    model_year = Column(Integer)
    mileage = Column(Integer)
    image_urls = Column(JSON)
    is_sold_out = Column(Boolean, default=False)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

async def scrape_goobike():
    async with async_playwright() as p:
        # ブラウザの起動
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36"
        )
        page = await context.new_page()

        # 1. 検索結果一覧ページへ (例: ホンダの車両)
        print("一覧ページを取得中...")
        target_url = "https://www.goobike.com/maker-honda/index.html"
        await page.goto(target_url, wait_until="networkidle")

        # 車両要素を取得 (セレクタは実際のサイト構造に合わせて調整が必要)
        bike_elements = await page.query_selector_all(".item_box")
        
        results = []
        for element in bike_elements[:5]: # テストとして5件のみ
            try:
                title_elem = await element.query_selector("h2")
                price_elem = await element.query_selector(".price_num")
                link_elem = await element.query_selector("a")
                
                if not title_elem or not price_elem or not link_elem:
                    continue

                title = await title_elem.inner_text()
                price_text = await price_elem.inner_text()
                link = await link_elem.get_attribute("href")
                
                print(f"発見: {title} - {price_text}")
                
                # 数値変換ロジック
                numeric_price = 0
                if '万円' in price_text:
                    numeric_price = int(float(price_text.replace('万円', '').replace(',', '').strip()) * 10000)

                results.append({
                    "source_platform": "GooBike",
                    "source_url": f"https://www.goobike.com{link}" if link.startswith('/') else link,
                    "price": numeric_price,
                })
            except Exception as e:
                print(f"パースエラー: {e}")

        # 2. データベースへ保存
        if results:
            db = SessionLocal()
            try:
                for item in results:
                    new_listing = Listing(
                        source_platform=item["source_platform"],
                        source_url=item["source_url"],
                        price=item["price"],
                        bike_model_id=1, # 仮
                        shop_id=1       # 仮
                    )
                    db.add(new_listing)
                db.commit()
                print(f"{len(results)} 件のデータを保存しました。")
            except Exception as e:
                print(f"DB保存エラー: {e}")
                db.rollback()
            finally:
                db.close()
        else:
            print("保存対象のデータが見つかりませんでした。")

        await browser.close()

if __name__ == "__main__":
    print("スクレイピングを開始します...")
    asyncio.run(scrape_goobike())