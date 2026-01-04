import asyncio
import os
import datetime
import re
import sys
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Text, DateTime, ForeignKey, UniqueConstraint, or_
from sqlalchemy.orm import DeclarativeBase, sessionmaker
from sqlalchemy.exc import IntegrityError

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

class Shop(Base):
    __tablename__ = "shops"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    name = Column(String(255), nullable=False)
    prefecture = Column(String(20), nullable=True)
    address = Column(Text, nullable=True)
    website_url = Column(Text, nullable=True)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

class ShopIdentifier(Base):
    __tablename__ = "shop_identifiers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    shop_id = Column(BigInteger, ForeignKey("shops.id", ondelete="CASCADE"), nullable=False)
    site_id = Column(BigInteger, ForeignKey("sites.id", ondelete="CASCADE"), nullable=False)
    identifier = Column(String(100), nullable=False)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)
    
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_shop_site_identifier_uc'),)

# 並列実行の設定
MAX_CONCURRENT_PAGES = 5
semaphore = asyncio.Semaphore(MAX_CONCURRENT_PAGES)

async def block_resources(route):
    """画像、CSS、フォントなどの不要なリソースを遮断"""
    if route.request.resource_type in ["image", "media", "font", "stylesheet"]:
        await route.abort()
    else:
        await route.continue_()

async def process_prefecture(context, pref, site_id, shop_cache, ident_cache):
    """1つの都道府県の店舗情報を収集するタスク"""
    async with semaphore:
        db = SessionLocal()
        page = await context.new_page()
        await page.route("**/*", block_resources)

        base_url = "https://www.goobike.com"
        current_page_url = pref['url']
        
        try:
            print(f"  [開始] {pref['name']}")
            while current_page_url:
                await page.goto(current_page_url, wait_until="domcontentloaded", timeout=60000)
                shop_elements = await page.query_selector_all(".shop_header")
                
                for shop_el in shop_elements:
                    try:
                        name_link_el = await shop_el.query_selector(".shop_name a")
                        if not name_link_el: continue
                        name = (await name_link_el.inner_text()).strip()
                        href = await name_link_el.get_attribute("href")
                        
                        identifier = None
                        if href:
                            match = re.search(r'client_(\d+)', href)
                            if match: identifier = match.group(1)

                        # JSでDOMから住所を取得
                        address = await page.evaluate("(el) => { const addr = el.parentElement.querySelector('.shop_address'); return addr ? addr.innerText : ''; }", shop_el)
                        address = address.strip()

                        # キャッシュによる重複チェック (名前+住所)
                        shop_id = shop_cache.get((name, address))
                        if not shop_id:
                            shop_record = Shop(
                                name=name, 
                                prefecture=pref['name'], 
                                address=address, 
                                website_url=base_url + href if href else None
                            )
                            db.add(shop_record)
                            db.flush()
                            shop_id = shop_record.id
                            shop_cache[(name, address)] = shop_id
                        
                        # 識別番号の登録
                        if identifier:
                            if (site_id, identifier) not in ident_cache:
                                db.add(ShopIdentifier(shop_id=shop_id, site_id=site_id, identifier=identifier))
                                ident_cache.add((site_id, identifier))
                        
                        db.commit()
                    except Exception:
                        db.rollback()

                # ページネーション処理
                next_button = await page.query_selector(".pager_next a")
                current_page_url = base_url + (await next_button.get_attribute("href")) if next_button else None
                
        except Exception as e:
            print(f"  [エラー] {pref['name']}: {e}")
        finally:
            db.close()
            await page.close()

async def collect():
    async with async_playwright() as p:
        print("GooBikeショップコレクター（セキュア・高速版）を起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        
        db = SessionLocal()
        goobike_site = db.query(Site).filter(Site.name == "GooBike").first()
        if not goobike_site:
            print("エラー: sitesテーブルに 'GooBike' が登録されていません。")
            return
        site_id = goobike_site.id

        # キャッシュの構築
        print("キャッシュを構築中...")
        shop_cache = {(s.name, s.address): s.id for s in db.query(Shop).all()}
        ident_cache = {(si.site_id, si.identifier) for si in db.query(ShopIdentifier).all()}

        try:
            # 都道府県一覧の取得
            temp_page = await context.new_page()
            await temp_page.route("**/*", block_resources)
            await temp_page.goto("https://www.goobike.com/shop/", wait_until="domcontentloaded")
            
            pref_links = await temp_page.query_selector_all(".mapBox li a")
            pref_urls = []
            for link in pref_links:
                href = await link.get_attribute("href")
                raw_pref_name = await link.inner_text()
                # 括弧内の台数表示等を除去
                pref_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', raw_pref_name).strip()
                if href:
                    pref_urls.append({"name": pref_name, "url": "https://www.goobike.com" + href})
            
            await temp_page.close()

            # 都道府県ごとに並列実行
            print(f"並列実行を開始します（最大 {MAX_CONCURRENT_PAGES} 並列）...")
            tasks = [
                process_prefecture(context, pref, site_id, shop_cache, ident_cache)
                for pref in pref_urls
            ]
            
            await asyncio.gather(*tasks)

            print("\nGooBike販売店データの収集が完了しました。")
        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())