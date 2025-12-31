import asyncio
import os
import datetime
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Numeric, Integer, Boolean, Text, JSON, DateTime
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# .envファイルを読み込む
# 1. カレントディレクトリ(scraper/)の.env
# 2. 親ディレクトリ(プロジェクトルート)の.env を順に探します
load_dotenv()
if not os.getenv("DB_DATABASE"):
    # コンテナ内では /app が scraper/ フォルダなので、親ディレクトリは / ですが
    # docker-composeでの構成上、../.env でプロジェクトルートの.envを参照します
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

# SQLAlchemy 2.0 形式のベースクラス定義 (警告回避)
class Base(DeclarativeBase):
    pass

# テーブル定義 (既存のテーブル構造に準拠)
class Listing(Base):
    __tablename__ = "listings"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    bike_model_id = Column(BigInteger, nullable=True)
    shop_id = Column(BigInteger, nullable=True)
    source_platform = Column(String(50))
    source_url = Column(Text, nullable=False)
    # Decimal ではなく Numeric を使用します（SQLAlchemyの標準）
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
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36"
        )
        page = await context.new_page()

        print("GooBikeの一覧ページにアクセス中...")
        target_url = "https://www.goobike.com/maker-honda/index.html"
        
        try:
            await page.goto(target_url, wait_until="networkidle", timeout=60000)
            
            # 車両情報が入っている要素を取得
            bike_elements = await page.query_selector_all(".item_box")
            
            if not bike_elements:
                print("車両要素が見つかりませんでした。セレクタを確認してください。")
                return

            results = []
            for element in bike_elements[:5]: # テスト用に最初の5件
                try:
                    title_elem = await element.query_selector("h2")
                    price_elem = await element.query_selector(".price_num")
                    link_elem = await element.query_selector("a")
                    
                    if title_elem and price_elem and link_elem:
                        title = await title_elem.inner_text()
                        price_text = await price_elem.inner_text()
                        link = await link_elem.get_attribute("href")
                        
                        # 価格変換ロジック
                        numeric_price = 0
                        clean_price = price_text.replace('万円', '').replace(',', '').strip()
                        # 数値(ドット含む)のみかチェック
                        import re
                        match = re.search(r'(\d+\.?\d*)', clean_price)
                        if match:
                            numeric_price = int(float(match.group(1)) * 10000)

                        results.append({
                            "source_platform": "GooBike",
                            "source_url": f"https://www.goobike.com{link}" if link.startswith('/') else link,
                            "price": numeric_price,
                        })
                        print(f"取得成功: {title.strip()} - {price_text.strip()}")
                except Exception as e:
                    print(f"個別パースエラー: {e}")

            # データベース保存処理
            if results:
                db = SessionLocal()
                try:
                    for item in results:
                        # 重複チェック（URLで判断）
                        existing = db.query(Listing).filter(Listing.source_url == item["source_url"]).first()
                        if not existing:
                            new_listing = Listing(**item)
                            db.add(new_listing)
                    db.commit()
                    print(f"完了: {len(results)}件のデータを処理しました。")
                except Exception as e:
                    print(f"DBエラー: {e}")
                    db.rollback()
                finally:
                    db.close()
            
        except Exception as e:
            print(f"ページアクセスエラー: {e}")
        finally:
            await browser.close()

if __name__ == "__main__":
    asyncio.run(scrape_goobike())