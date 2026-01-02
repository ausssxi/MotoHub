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

# サイトマスタ
class Site(Base):
    __tablename__ = "sites"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(50), unique=True)

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

# 店舗認識番号テーブル定義
class ShopIdentifier(Base):
    __tablename__ = "shop_identifiers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    shop_id = Column(BigInteger, ForeignKey("shops.id", ondelete="CASCADE"), nullable=False)
    site_id = Column(BigInteger, ForeignKey("sites.id", ondelete="CASCADE"), nullable=False)
    identifier = Column(String(100), nullable=False)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)
    
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_shop_site_identifier_uc'),)

async def collect_bds_shops():
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
        
        # BDSのサイトIDを取得
        bds_site = db.query(Site).filter(Site.name == "BDS").first()
        if not bds_site:
            print("エラー: sitesテーブルに 'BDS' が登録されていません。")
            db.close()
            await browser.close()
            return
        site_id = bds_site.id

        # 都道府県コード 01 ～ 47 のマップ
        pref_map = {
            "01": "北海道", "02": "青森", "03": "岩手", "04": "宮城", "05": "秋田", "06": "山形", "07": "福島",
            "08": "茨城", "09": "栃木", "10": "群馬", "11": "埼玉", "12": "千葉", "13": "東京", "14": "神奈川",
            "15": "新潟", "16": "富山", "17": "石川", "18": "福井", "19": "山梨", "20": "長野", "21": "岐阜",
            "22": "静岡", "23": "愛知", "24": "三重", "25": "滋賀", "26": "京都", "27": "大阪", "28": "兵庫",
            "29": "奈良", "30": "和歌山", "31": "鳥取", "32": "島根", "33": "岡山", "34": "広島", "35": "山口",
            "36": "徳島", "37": "香川", "38": "愛媛", "39": "高知", "40": "福岡", "41": "佐賀", "42": "長崎",
            "43": "熊本", "44": "大分", "45": "宮崎", "46": "鹿児島", "47": "沖縄"
        }

        try:
            for code, pref_name in pref_map.items():
                current_url = f"{base_url}/shop?prefectureCodes%5B%5D={code}"
                print(f"\n--- 都道府県: {pref_name} ({code}) を収集開始 ---")
                
                page_num = 1
                while current_url:
                    print(f"  解析中 P.{page_num}: {current_url}")
                    await page.goto(current_url, wait_until="domcontentloaded", timeout=60000)
                    
                    # 各ショップのリストアイテムを取得
                    shop_items = await page.query_selector_all("li.c-search_block_list_item.type_shop")
                    
                    if not shop_items:
                        break

                    for item in shop_items:
                        try:
                            # 1. 店名と認識番号が含まれるリンクの取得
                            name_el = await item.query_selector(".c-search_block_shop_title01 a")
                            if not name_el: continue
                            name = (await name_el.inner_text()).strip()

                            # 詳細URLの取得 (例: /shop/client/60534)
                            href = await name_el.get_attribute("href")
                            detail_url = base_url + href if href.startswith('/') else href

                            # 2. 認識番号の抽出 (client/ 以降の数字)
                            identifier = None
                            if href:
                                match = re.search(r'client/(\d+)', href)
                                if match:
                                    identifier = match.group(1)

                            # 3. 住所と電話番号の取得 (テーブル構造)
                            address = ""
                            phone = ""
                            table_rows = await item.query_selector_all(".c-search_block_shop-info_table table tr")
                            for row in table_rows:
                                th = await row.query_selector("th")
                                td = await row.query_selector("td")
                                if th and td:
                                    header = await th.inner_text()
                                    if "住所" in header:
                                        address = (await td.inner_text()).strip()
                                    elif "電話番号" in header:
                                        phone = (await td.inner_text()).strip()

                            if not name or not address:
                                continue

                            # 4. 重複チェック (名前 または 住所)
                            existing_shop = db.query(Shop).filter(
                                or_(Shop.name == name, Shop.address == address)
                            ).first()

                            if not existing_shop:
                                existing_shop = Shop(
                                    name=name,
                                    prefecture=pref_name,
                                    address=address,
                                    phone=phone,
                                    website_url=detail_url
                                )
                                db.add(existing_shop)
                                db.flush() # ID確定
                                print(f"    [新店登録] {name}")
                            
                            # 5. ShopIdentifierの登録
                            if identifier:
                                existing_ident = db.query(ShopIdentifier).filter(
                                    ShopIdentifier.site_id == site_id,
                                    ShopIdentifier.identifier == identifier
                                ).first()

                                if not existing_ident:
                                    new_ident = ShopIdentifier(
                                        shop_id=existing_shop.id,
                                        site_id=site_id,
                                        identifier=identifier
                                    )
                                    db.add(new_ident)
                                    print(f"      -> ID登録: {identifier}")

                            db.commit()
                        except Exception as e:
                            print(f"    店舗個別解析エラー: {e}")
                            db.rollback()

                    # 6. ページネーション (次へボタン)
                    next_btn = await page.query_selector("div.c-pager a.c-btn_next")
                    if next_btn:
                        href = await next_btn.get_attribute("href")
                        current_url = base_url + href if href.startswith('/') else href
                        page_num += 1
                        await asyncio.sleep(1)
                    else:
                        current_url = None

            print("\nBDS販売店の全件収集が完了しました。")

        except Exception as e:
            print(f"致命的なエラー: {e}")
        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect_bds_shops())