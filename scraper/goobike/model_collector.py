import asyncio
import os
import datetime
import re
import sys
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, ForeignKey, UniqueConstraint
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# 1. 環境変数の読み込み
# 現在のファイル位置 (scraper/goobike/) から見て、1つ上の階層 (scraper/) にある .env を探す
current_dir = os.path.dirname(os.path.abspath(__file__))
env_path = os.path.join(current_dir, '..', '.env')
load_dotenv(dotenv_path=env_path)

# もし読み込めなかったらカレントディレクトリも確認
if not os.getenv("DB_DATABASE"):
    load_dotenv()

def get_env_or_exit(key, default=None, required=True):
    """
    環境変数を取得する。
    required=True の場合、値が取得できなければプログラムを終了させる（セキュリティ対策）。
    """
    val = os.getenv(key, default)
    if required and val is None:
        print(f"致命的エラー: 必須の環境変数 '{key}' が設定されていません。")
        sys.exit(1)
    return val

# データベース接続設定: 機密情報はデフォルト値を設定せず必須（required=True）とする
DB_USER = get_env_or_exit("DB_USERNAME")
DB_PASS = get_env_or_exit("DB_PASSWORD")
DB_NAME = get_env_or_exit("DB_DATABASE")

# 接続先やポートは、機密情報ではないため利便性のためにデフォルト値を残しても許容される
DB_HOST = get_env_or_exit("DB_HOST", default="db")
DB_PORT = get_env_or_exit("DB_PORT", default="3306")

DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}"

engine = create_engine(DATABASE_URL)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

class Base(DeclarativeBase):
    pass

class Site(Base):
    __tablename__ = "sites"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(50), unique=True)

class Manufacturer(Base):
    __tablename__ = "manufacturers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    name = Column(String(100), unique=True, nullable=False)
    country = Column(String(50), nullable=True)

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    manufacturer_id = Column(BigInteger, nullable=False)
    name = Column(String(255), nullable=False, unique=True)
    category = Column(String(50), nullable=True)
    displacement = Column(Integer, nullable=True)

class BikeModelIdentifier(Base):
    __tablename__ = "bike_model_identifiers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    bike_model_id = Column(BigInteger, ForeignKey("bike_models.id", ondelete="CASCADE"), nullable=False)
    site_id = Column(BigInteger, ForeignKey("sites.id", ondelete="CASCADE"), nullable=False)
    identifier = Column(String(100), nullable=False)
    
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_site_identifier_uc'),)

async def collect():
    async with async_playwright() as p:
        print("GooBikeモデルコレクター（セキュア版）を起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        page = await context.new_page()
        base_url = "https://www.goobike.com"
        db = SessionLocal()
        
        goobike_site = db.query(Site).filter(Site.name == "GooBike").first()
        if not goobike_site:
            print("エラー: sitesテーブルに 'GooBike' が登録されていません。")
            return
        site_id = goobike_site.id

        try:
            print(f"メーカー一覧を取得中...")
            await page.goto(f"{base_url}/maker-top/index.html", wait_until="domcontentloaded", timeout=60000)
            country_elements = await page.query_selector_all("p.title")
            maker_targets = []

            for country_el in country_elements:
                country_name = (await country_el.inner_text()).strip()
                # JSコンテキストで隣接するテーブルからメーカーリンクを抽出
                makers = await page.evaluate("(el) => { let table = el.nextElementSibling; while(table && table.tagName !== 'TABLE') { table = table.nextElementSibling; } if (!table) return []; const links = table.querySelectorAll('span.mj a'); return Array.from(links).map(a => ({ name: a.innerText, href: a.getAttribute('href') })); }", country_el)

                for link_info in makers:
                    # 全角括弧などを除去
                    clean_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', link_info['name']).strip()
                    if clean_name:
                        maker_targets.append({"name": clean_name, "country": country_name, "url": base_url + link_info['href']})
                        m_record = db.query(Manufacturer).filter(Manufacturer.name == clean_name).first()
                        if not m_record:
                            m_record = Manufacturer(name=clean_name, country=country_name)
                            db.add(m_record)
                            db.flush()
            db.commit()

            for target in maker_targets:
                print(f"--- {target['name']} の車種を取得中 ---")
                m_record = db.query(Manufacturer).filter(Manufacturer.name == target['name']).first()
                try:
                    await page.goto(target['url'], wait_until="domcontentloaded", timeout=60000)
                    list_items = await page.query_selector_all("li.bike_list")
                    for item in list_items:
                        name_elem = await item.query_selector("em b")
                        if not name_elem: continue
                        model_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', await name_elem.inner_text()).strip()
                        input_elem = await item.query_selector("input[name='model']")
                        identifier_val = await input_elem.get_attribute("value") if input_elem else None

                        if not model_name: continue
                        
                        existing_model = db.query(BikeModel).filter(BikeModel.name == model_name).first()
                        if not existing_model:
                            existing_model = BikeModel(
                                name=model_name, 
                                manufacturer_id=m_record.id, 
                                category="不明",
                                displacement=None
                            )
                            db.add(existing_model)
                            db.flush()

                        if identifier_val:
                            existing_idnt = db.query(BikeModelIdentifier).filter(BikeModelIdentifier.site_id == site_id, BikeModelIdentifier.identifier == identifier_val).first()
                            if not existing_idnt:
                                db.add(BikeModelIdentifier(bike_model_id=existing_model.id, site_id=site_id, identifier=identifier_val))
                    db.commit()
                except Exception as e:
                    print(f"エラー ({target['name']}): {e}")
                    db.rollback()

            print("\nGooBike車種マスタ同期が完了しました。")
        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())