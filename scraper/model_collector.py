import asyncio
import os
import datetime
import re
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime
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

# メーカー情報テーブル
class Manufacturer(Base):
    __tablename__ = "manufacturers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    name = Column(String(100), unique=True, nullable=False)
    name_kana = Column(String(100), nullable=True)
    country = Column(String(50), nullable=True)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

# 車種マスタテーブル
class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    manufacturer_id = Column(BigInteger, nullable=False)
    name = Column(String(255), nullable=False)
    displacement = Column(Integer, nullable=True)
    category = Column(String(50), nullable=True)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

async def collect_data():
    async with async_playwright() as p:
        print("ブラウザを起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36"
        )
        page = await context.new_page()

        base_url = "https://www.goobike.com"
        db = SessionLocal()
        
        try:
            # --- 1. メーカーと国籍の紐付け収集 ---
            print(f"メーカー一覧を取得中: {base_url}/maker-top/index.html")
            await page.goto(f"{base_url}/maker-top/index.html", wait_until="domcontentloaded", timeout=60000)
            
            country_elements = await page.query_selector_all("p.title")
            maker_targets = []

            for country_el in country_elements:
                country_name = await country_el.inner_text()
                country_name = country_name.strip()
                
                maker_links = await page.evaluate(
                    """(el) => {
                        let table = el.nextElementSibling;
                        while(table && table.tagName !== 'TABLE') {
                            table = table.nextElementSibling;
                        }
                        if (!table) return [];
                        const links = table.querySelectorAll('span.mj a');
                        return Array.from(links).map(a => ({
                            name: a.innerText,
                            href: a.getAttribute('href')
                        }));
                    }""", country_el
                )

                for link_info in maker_links:
                    clean_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', link_info['name']).strip()
                    if clean_name:
                        maker_targets.append({
                            "name": clean_name,
                            "country": country_name,
                            "url": base_url + link_info['href'] if link_info['href'].startswith('/') else link_info['href']
                        })
                        
                        m_record = db.query(Manufacturer).filter(Manufacturer.name == clean_name).first()
                        if not m_record:
                            m_record = Manufacturer(name=clean_name, country=country_name)
                            db.add(m_record)
                            db.flush()
                            print(f"[メーカー新登録] {clean_name} ({country_name})")
                        else:
                            m_record.country = country_name
            
            db.commit()

            # --- 2. 各メーカーの車種収集 (名前のみ) ---
            for target in maker_targets:
                print(f"--- {target['name']} の車種を取得中 ---")
                m_record = db.query(Manufacturer).filter(Manufacturer.name == target['name']).first()
                try:
                    await page.goto(target['url'], wait_until="domcontentloaded", timeout=60000)
                    bike_elements = await page.query_selector_all("li.bike_list em b")
                    for bike_elem in bike_elements:
                        full_text = await bike_elem.inner_text()
                        model_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', full_text).strip()
                        if not model_name: continue
                        
                        existing = db.query(BikeModel).filter(
                            BikeModel.name == model_name,
                            BikeModel.manufacturer_id == m_record.id
                        ).first()

                        if not existing:
                            db.add(BikeModel(name=model_name, manufacturer_id=m_record.id, category="不明"))
                    db.commit()
                except Exception as e:
                    print(f"エラー ({target['name']}): {e}")

            # --- 3. バイクスタイルの同期 (ジャンル別巡回) ---
            print("\n--- バイクスタイル(ジャンル)の同期を開始します ---")
            # 通常 01 (ネイキッド) から 16 (スクランブラー) 程度まで存在します
            for i in range(1, 17):
                genre_id = str(i).zfill(2)
                genre_url = f"{base_url}/genre-{genre_id}/index.html"
                
                try:
                    print(f"ジャンル {genre_id} を解析中: {genre_url}")
                    await page.goto(genre_url, wait_until="domcontentloaded", timeout=60000)
                    
                    # パンくずリストやヘッダーからスタイル名を取得
                    # <li><a href='/'>バイクTOP</a></li><li><strong>ネイキッド</strong></li>
                    style_elem = await page.query_selector("li strong")
                    if not style_elem:
                        continue
                    
                    style_name = await style_elem.inner_text()
                    print(f"スタイル名: {style_name}")

                    # このページに掲載されている車種をすべて取得
                    bike_elements = await page.query_selector_all("li.bike_list em b")
                    
                    updated_count = 0
                    for bike_elem in bike_elements:
                        full_text = await bike_elem.inner_text()
                        model_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', full_text).strip()
                        
                        if not model_name:
                            continue

                        # DB内の該当する車種のカテゴリーを一括更新
                        # (メーカーを跨いで同じ名前の車種がある可能性を考慮し、全一致する車種を更新)
                        targets = db.query(BikeModel).filter(BikeModel.name == model_name).all()
                        for t in targets:
                            if t.category != style_name:
                                t.category = style_name
                                t.updated_at = datetime.datetime.now()
                                updated_count += 1
                    
                    db.commit()
                    print(f"-> {updated_count} 件の車種にスタイル '{style_name}' を適用しました。")
                    await asyncio.sleep(1)

                except Exception as e:
                    print(f"ジャンル解析エラー (ID: {genre_id}): {e}")

            print("\nすべての同期が完了しました。")

        except Exception as e:
            print(f"致命的エラー: {e}")
        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect_data())