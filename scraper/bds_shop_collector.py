import asyncio
import os
import datetime
import re
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Text, DateTime, or_
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

async def collect_bds_shops():
    async with async_playwright() as p:
        print("BDSショップコレクターを起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
            viewport={'width': 1920, 'height': 1080}
        )
        page = await context.new_page()

        base_url = "https://www.bds-bikesensor.net"
        db = SessionLocal()
        
        # 都道府県コード 01 ～ 47
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
                # 都道府県別URLの生成
                current_url = f"{base_url}/shop?prefectureCodes%5B%5D={code}"
                print(f"\n--- 都道府県: {pref_name} ({code}) を収集開始 ---")
                
                page_num = 1
                while current_url:
                    print(f"  解析中 P.{page_num}: {current_url}")
                    await page.goto(current_url, wait_until="domcontentloaded", timeout=60000)
                    
                    # 各ショップのリストアイテムを取得
                    shop_items = await page.query_selector_all("li.c-search_block_list_item.type_shop")
                    
                    if not shop_items:
                        print("    ショップが見つかりませんでした。次の都道府県へ移動します。")
                        break

                    for item in shop_items:
                        try:
                            # 1. 店名の取得
                            name_el = await item.query_selector(".c-search_block_shop_title01 a")
                            if not name_el: continue
                            name = (await name_el.inner_text()).strip()

                            # 2. 詳細URLの取得
                            href = await name_el.get_attribute("href")
                            detail_url = base_url + href if href.startswith('/') else href

                            # 3. 住所・電話番号の取得 (テーブル構造の解析)
                            address = ""
                            phone = ""
                            rows = await item.query_selector_all(".c-search_block_shop-info_table table tr")
                            for row in rows:
                                th = await row.query_selector("th")
                                td = await row.query_selector("td")
                                if th and td:
                                    header_text = await th.inner_text()
                                    if "住所" in header_text:
                                        address = (await td.inner_text()).strip()
                                    elif "電話番号" in header_text:
                                        phone = (await td.inner_text()).strip()

                            if not name or not address:
                                continue

                            # 4. 重複チェック (名前 もしくは 住所 が一致するか)
                            # or_ を使用して、どちらかがDBに存在すればスキップします
                            existing = db.query(Shop).filter(
                                or_(Shop.name == name, Shop.address == address)
                            ).first()

                            if not existing:
                                new_shop = Shop(
                                    name=name,
                                    prefecture=pref_name,
                                    address=address,
                                    phone=phone,
                                    website_url=detail_url
                                )
                                db.add(new_shop)
                                db.flush() # ID確定
                                print(f"    [登録] {name}")
                            else:
                                # 重複時はログを出さず静かにスキップ
                                pass
                            
                            db.commit()
                        except Exception as e:
                            print(f"    店舗解析エラー: {e}")
                            db.rollback()

                    # 5. ページネーション (次へボタン)
                    next_btn = await page.query_selector("div.c-pager a.c-btn_next")
                    if next_btn:
                        href = await next_btn.get_attribute("href")
                        current_url = base_url + href if href.startswith('/') else href
                        page_num += 1
                        await asyncio.sleep(1) # 負荷軽減
                    else:
                        current_url = None

            print("\nBDS販売店の収集が完了しました。")

        except Exception as e:
            print(f"致命的エラー: {e}")
        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect_bds_shops())