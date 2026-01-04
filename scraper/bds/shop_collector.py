import asyncio
import os
import datetime
import re
import unicodedata
import random
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Text, DateTime, ForeignKey, UniqueConstraint, or_
from sqlalchemy.orm import DeclarativeBase, sessionmaker
from sqlalchemy.exc import IntegrityError

# 環境変数の読み込み
load_dotenv()
if not os.getenv("DB_DATABASE"):
    load_dotenv(dotenv_path='../../.env')

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

class Site(Base):
    __tablename__ = "sites"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(50), unique=True)

class Shop(Base):
    __tablename__ = "shops"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    name = Column(String(255), nullable=False)
    prefecture = Column(String(20), nullable=True)
    address = Column(String(255), nullable=False, unique=True)
    phone = Column(String(20), nullable=True)
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

def normalize_text(text: str) -> str:
    """
    日本の住所表記のゆれを吸収する高度な正規化
    1. 全角を半角へ (NFKC)
    2. 空白の完全除去
    3. 「丁目」「番地」「番」「号」をハイフンに置換
    4. ハイフン類の統一と連続ハイフンの集約
    """
    if not text:
        return ""
    # NFKC正規化 (全角英数 -> 半角)
    text = unicodedata.normalize('NFKC', text)
    # 小文字化と空白除去
    text = re.sub(r'\s+', '', text).lower()
    # 住所の「丁目」「番地」「番」「号」をハイフンに置き換える
    text = re.sub(r'[丁目|番地|番|号]', '-', text)
    # 特殊なハイフン記号を標準のハイフンに統一
    text = re.sub(r'[－ー−―‐－-]', '-', text)
    # 連続するハイフンを1つにまとめる
    text = re.sub(r'-+', '-', text)
    # 末尾のハイフンを削除
    return text.strip('-')

async def block_resources(route):
    """不要なリソースの読み込みを遮断"""
    if route.request.resource_type in ["image", "media", "font", "stylesheet"]:
        await route.abort()
    else:
        await route.continue_()

async def process_prefecture(context, code, pref_name, site_id, shop_cache, ident_cache):
    """1つの都道府県の店舗情報を並列で収集するタスク"""
    async with semaphore:
        db = SessionLocal()
        page = await context.new_page()
        await page.route("**/*", block_resources)

        base_url = "https://www.bds-bikesensor.net"
        current_url = f"{base_url}/shop?prefectureCodes%5B%5D={code}"
        
        try:
            print(f"  [開始] {pref_name}")
            while current_url:
                await page.goto(current_url, wait_until="domcontentloaded", timeout=60000)
                shop_items = await page.query_selector_all("li.c-search_block_list_item.type_shop")
                
                if not shop_items:
                    break

                for item in shop_items:
                    try:
                        # 店名取得
                        name_el = await item.query_selector(".c-search_block_shop_title01 a")
                        if not name_el: continue
                        raw_name = (await name_el.inner_text()).strip()
                        href = await name_el.get_attribute("href")
                        
                        identifier = None
                        if href:
                            match = re.search(r'client/(\d+)', href)
                            if match: identifier = match.group(1)

                        # 住所と電話番号の取得
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

                        if not raw_name or not address: continue

                        # --- 表記ゆれ対策: 高度な正規化 ---
                        norm_name = normalize_text(raw_name)
                        norm_address = normalize_text(address)

                        shop_id = None
                        
                        # 1. キャッシュから店名が一致する既存店を探す
                        candidates = shop_cache.get(norm_name, [])
                        for cached_norm_addr, cached_real_addr, cached_id in candidates:
                            # 住所が「どちらか一方がもう一方を含む」なら同一店とみなす
                            # 高度な正規化により「1-1-7」と「1丁目1-7」は共に「1-1-7」に変換されるため一致する
                            if norm_address in cached_norm_addr or cached_norm_addr in norm_address:
                                shop_id = cached_id
                                break

                        # 2. それでもない場合は新規登録
                        if not shop_id:
                            try:
                                shop_record = Shop(
                                    name=raw_name,
                                    prefecture=pref_name,
                                    address=address,
                                    phone=phone,
                                    website_url=(href if href.startswith('http') else base_url + href) if href else None
                                )
                                db.add(shop_record)
                                db.flush()
                                shop_id = shop_record.id
                                
                                # キャッシュに追加
                                if norm_name not in shop_cache:
                                    shop_cache[norm_name] = []
                                shop_cache[norm_name].append((norm_address, address, shop_id))
                                
                            except IntegrityError:
                                db.rollback()
                                db_shop = db.query(Shop).filter(Shop.address == address).first()
                                if db_shop:
                                    shop_id = db_shop.id

                        # 3. 識別番号の登録
                        if shop_id and identifier:
                            if (site_id, identifier) not in ident_cache:
                                try:
                                    db.add(ShopIdentifier(shop_id=shop_id, site_id=site_id, identifier=identifier))
                                    db.commit()
                                    ident_cache.add((site_id, identifier))
                                except IntegrityError:
                                    db.rollback()
                        
                        db.commit()
                    except Exception as e:
                        db.rollback()
                        print(f"      解析エラー: {e}")

                # ページネーション処理
                next_btn = await page.query_selector("div.c-pager a.c-btn_next")
                if next_btn:
                    href = await next_btn.get_attribute("href")
                    current_url = href if href.startswith('http') else base_url + (href if href.startswith('/') else '/' + href)
                    await asyncio.sleep(0.5)
                else:
                    current_url = None

        except Exception as e:
            print(f"  [エラー] {pref_name}: {e}")
        finally:
            db.close()
            await page.close()

async def collect():
    async with async_playwright() as p:
        print("BDSショップコレクター（住所ゆれ対応版）を起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            viewport={'width': 1280, 'height': 800}
        )
        
        db = SessionLocal()
        bds_site = db.query(Site).filter(Site.name == "BDS").first()
        if not bds_site:
            print("エラー: sitesテーブルに 'BDS' が登録されていません。")
            return
        site_id = bds_site.id

        print("名寄せ用キャッシュを構築中...")
        shop_cache = {}
        for s in db.query(Shop).all():
            n_name = normalize_text(s.name)
            n_addr = normalize_text(s.address) # ここでも高度な正規化を適用
            if n_name not in shop_cache:
                shop_cache[n_name] = []
            shop_cache[n_name].append((n_addr, s.address, s.id))

        ident_cache = {(si.site_id, si.identifier) for si in db.query(ShopIdentifier).all()}
        db.close()

        pref_map = {
            "01": "北海道", "02": "青森", "03": "岩手", "04": "宮城", "05": "秋田", "06": "山形", "07": "福島",
            "08": "茨城", "09": "栃木", "10": "群馬", "11": "埼玉", "12": "千葉", "13": "東京", "14": "神奈川",
            "15": "新潟", "16": "富山", "17": "石川", "18": "福井", "19": "山梨", "20": "長野", "21": "岐阜",
            "22": "静岡", "23": "愛知", "24": "三重", "25": "滋賀", "26": "京都", "27": "大阪", "28": "兵庫",
            "29": "奈良", "30": "和歌山", "31": "鳥取", "32": "島根", "33": "岡山", "34": "広島", "35": "山口",
            "36": "徳島", "37": "香川", "38": "愛媛", "39": "高知", "40": "福岡", "41": "佐賀", "42": "長崎",
            "43": "熊本", "44": "大分", "45": "宮崎", "46": "鹿児島", "47": "沖縄"
        }

        tasks = [
            process_prefecture(context, code, name, site_id, shop_cache, ident_cache)
            for code, name in pref_map.items()
        ]
        
        await asyncio.gather(*tasks)

        print("\nBDS販売店データの全件収集が完了しました。")
        await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())