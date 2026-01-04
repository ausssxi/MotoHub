import asyncio
import os
import datetime
import re
import json
import random
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Numeric, Integer, Boolean, Text, JSON, DateTime, ForeignKey, select, or_
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
    site_id = Column(BigInteger, nullable=False)
    title = Column(String(255), nullable=True)
    source_url = Column(Text, nullable=False)
    price = Column(Numeric(12, 0))
    total_price = Column(Numeric(12, 0), nullable=True)
    model_year = Column(Integer, nullable=True)
    mileage = Column(Integer, nullable=True)
    image_urls = Column(JSON, nullable=True)
    is_sold_out = Column(Boolean, default=False)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

class BikeModelIdentifier(Base):
    __tablename__ = "bike_model_identifiers"
    id = Column(BigInteger, primary_key=True)
    bike_model_id = Column(BigInteger, nullable=False)
    site_id = Column(BigInteger, nullable=False)
    identifier = Column(String(100), nullable=False)

class ShopIdentifier(Base):
    __tablename__ = "shop_identifiers"
    id = Column(BigInteger, primary_key=True)
    shop_id = Column(BigInteger, nullable=False)
    site_id = Column(BigInteger, nullable=False)
    identifier = Column(String(100), nullable=False)

class Site(Base):
    __tablename__ = "sites"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(50))

class Shop(Base):
    __tablename__ = "shops"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(255))
    address = Column(Text)

# 同時接続数を制限（BDSは過剰アクセスに厳しいため 3 程度が安全）
MAX_CONCURRENT_PAGES = 3
semaphore = asyncio.Semaphore(MAX_CONCURRENT_PAGES)

async def block_resources(route):
    """画像（メイン以外）、CSS、フォントなどの不要なリソースを遮断"""
    # 画像は data-src を取得するだけであればブロックして問題ありませんが、
    # 描画を待つ必要がある場合は image を許可する必要があります。
    # ここではテキストと属性のみ抽出するため image もブロックして高速化します。
    if route.request.resource_type in ["image", "media", "font", "stylesheet"]:
        await route.abort()
    else:
        await route.continue_()

async def process_model_page(context, base_url, model_path, bike_model_id, site_id, shop_cache, known_urls):
    """車種ごとの出品一覧を解析するタスク。リトライ機能付き。"""
    async with semaphore:
        db = SessionLocal()
        page = await context.new_page()
        await page.route("**/*", block_resources)
        
        target_url = model_path if model_path.startswith('http') else base_url + model_path
        max_retries = 3
        retry_count = 0
        success = False

        while retry_count < max_retries and not success:
            try:
                # 接続拒否対策：リトライ時は待機時間を設ける
                if retry_count > 0:
                    wait = (retry_count * 3) + random.random()
                    await asyncio.sleep(wait)

                await page.goto(target_url, wait_until="domcontentloaded", timeout=60000)
                success = True

                # 出品ブロックの取得
                bike_blocks = await page.query_selector_all("li.type_bike, li.type_bike_sp")
                new_records = 0

                for bike in bike_blocks:
                    try:
                        title_el = await bike.query_selector(".c-search_block_title a, .c-search_block_title02 a")
                        if not title_el: continue
                        
                        v_url = base_url + (await title_el.get_attribute("href"))

                        # --- 重複スキップ ---
                        if v_url in known_urls:
                            continue

                        v_title = (await title_el.inner_text()).strip()

                        # 価格取得
                        price_val, total_price_val = 0, None
                        price_items = await bike.query_selector_all(".c-search_block_price")
                        for p_item in price_items:
                            label = await p_item.query_selector(".c-search_block_price_title")
                            value = await p_item.query_selector(".c-search_block_price_text")
                            if label and value:
                                l_text = await label.inner_text()
                                v_text = (await value.inner_text()).replace(',', '').replace('\n', '').strip()
                                match = re.search(r'(\d+\.?\d*)', v_text)
                                if match:
                                    num = int(float(match.group(1)) * 10000)
                                    if "本体価格" in l_text: price_val = num
                                    elif "支払総額" in l_text: total_price_val = num

                        # スペック取得
                        year, mile = None, None
                        status_cols = await bike.query_selector_all(".c-search_status_col")
                        for col in status_cols:
                            h_el = await col.query_selector(".c-search_status_head")
                            v_el = await col.query_selector(".c-search_status_title01")
                            if h_el and v_el:
                                h_txt = await h_el.inner_text()
                                v_txt = await v_el.inner_text()
                                if "モデル年" in h_txt and "不明" not in v_txt:
                                    y_m = re.search(r'(\d{4})', v_txt)
                                    if y_m: year = int(y_m.group(1))
                                elif "距離" in h_txt:
                                    m_m = re.search(r'(\d+)', v_txt.replace(',', ''))
                                    if m_m: mile = int(m_m.group(1))

                        # 画像取得処理
                        images = []
                        img_el = await bike.query_selector(".c-bike_image figure.c-img_cover")
                        if img_el:
                            img_src = await img_el.get_attribute("data-src") or await img_el.get_attribute("src")
                            if img_src and "blank" not in img_src:
                                images.append(img_src)

                        # 販売店特定
                        shop_id = None
                        shop_detail_link_el = await bike.query_selector(".c-search_block_bottom_lead a")
                        if shop_detail_link_el:
                            shop_href = await shop_detail_link_el.get_attribute("href")
                            id_match = re.search(r'client/(\d+)', shop_href)
                            if id_match:
                                shop_id = shop_cache.get(id_match.group(1))

                        # 保存
                        new_listing = Listing(
                            bike_model_id=bike_model_id,
                            shop_id=shop_id,
                            site_id=site_id,
                            title=v_title,
                            source_url=v_url,
                            price=price_val,
                            total_price=total_price_val,
                            model_year=year,
                            mileage=mile,
                            image_urls=images,
                            is_sold_out=False
                        )
                        db.add(new_listing)
                        db.commit()
                        known_urls.add(v_url)
                        new_records += 1

                    except Exception as e:
                        db.rollback()
                        print(f"      車両解析エラー: {e}")

                if new_records > 0:
                    print(f"    [完了] {model_path}: {new_records}件の新着を登録")

            except Exception as e:
                retry_count += 1
                if retry_count == max_retries:
                    print(f"    [エラー] 車種ページ取得失敗 ({model_path}): {e}")
            finally:
                if success or retry_count == max_retries:
                    db.close()
                    await page.close()

async def collect():
    db = SessionLocal()
    site = db.query(Site).filter(Site.name == "BDS").first()
    if not site:
        print("エラー: sitesテーブルに 'BDS' が見つかりません。")
        return
    site_id = site.id

    # キャッシュの構築
    print("キャッシュを構築中...")
    model_ident_cache = {i.identifier: i.bike_model_id for i in db.query(BikeModelIdentifier).filter(BikeModelIdentifier.site_id == site_id).all()}
    shop_cache = {i.identifier: i.shop_id for i in db.query(ShopIdentifier).filter(ShopIdentifier.site_id == site_id).all()}
    known_urls = {l.source_url for l in db.query(Listing.source_url).filter(Listing.site_id == site_id).all()}
    print(f"既知のURLを {len(known_urls)} 件ロードしました。")
    db.close()

    async with async_playwright() as p:
        print("BDSリスティングコレクター（高速・フルリスト版）を起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        
        base_url = "https://www.bds-bikesensor.net"
        
        # 巡回メーカーフルリスト
        maker_list = [
            {"slug": "honda", "name": "ホンダ"}, {"slug": "suzuki", "name": "スズキ"},
            {"slug": "yamaha", "name": "ヤマハ"}, {"slug": "kawasaki", "name": "カワサキ"},
            {"slug": "daihatsu", "name": "ダイハツ"}, {"slug": "bridgestone", "name": "ブリジストン"},
            {"slug": "meguro", "name": "メグロ"}, {"slug": "rodeo", "name": "ロデオ"},
            {"slug": "plot", "name": "プロト"}, {"slug": "bmw", "name": "BMW"},
            {"slug": "ktm", "name": "KTM"}, {"slug": "aprilia", "name": "アプリリア"},
            {"slug": "mv_agusta", "name": "MVアグスタ"}, {"slug": "gilera", "name": "ジレラ"},
            {"slug": "ducati", "name": "ドゥカティ"}, {"slug": "triumph", "name": "トライアンフ"},
            {"slug": "norton", "name": "ノートン"}, {"slug": "harley_davidson", "name": "ハーレーダビッドソン"},
            {"slug": "husqvarna", "name": "ハスクバーナ"}, {"slug": "bimota", "name": "ビモータ"},
            {"slug": "buell", "name": "ビューエル"}, {"slug": "vespa", "name": "ベスパ"},
            {"slug": "moto_guzzi", "name": "モトグッツィ"}, {"slug": "royal_enfield", "name": "ロイヤルエンフィールド"},
            {"slug": "daelim", "name": "DAELIM"}, {"slug": "gg", "name": "GG"},
            {"slug": "pgo", "name": "PGO"}, {"slug": "sym", "name": "SYM"},
            {"slug": "italjet", "name": "イタルジェット"}, {"slug": "gasgas", "name": "ガスガス"},
            {"slug": "kymco", "name": "キムコ"}, {"slug": "krauser", "name": "クラウザー"},
            {"slug": "sachs", "name": "ザックス"}, {"slug": "derbi", "name": "デルビ"},
            {"slug": "tomos", "name": "トモス"}, {"slug": "piaggio", "name": "ピアジオ"},
            {"slug": "bsa", "name": "ビーエスエー"}, {"slug": "fantic", "name": "ファンティック"},
            {"slug": "peugeot", "name": "プジョー"}, {"slug": "beta", "name": "ベータ"},
            {"slug": "benelli", "name": "ベネリ"}, {"slug": "magni", "name": "マーニ"},
            {"slug": "moto_morini", "name": "モトモリーニ"}, {"slug": "mondial", "name": "モンディアル"},
            {"slug": "montesa", "name": "モンテッサ"}, {"slug": "lambretta", "name": "ランブレッタ"},
            {"slug": "adiva", "name": "アディバ"}, {"slug": "megelli", "name": "メガリ"},
            {"slug": "indian", "name": "インディアン"}, {"slug": "gpx", "name": "GPX"},
            {"slug": "phoenix", "name": "PHOENIX"}, {"slug": "leonart", "name": "レオンアート"},
            {"slug": "brp", "name": "BRP"}, {"slug": "brixton", "name": "BRIXTON"},
            {"slug": "mutt", "name": "MUTT"},
        ]

        try:
            for m in maker_list:
                m_url = f"{base_url}/bike/maker/{m['slug']}"
                print(f"\n--- {m['name']} の出品情報をスキャン中 ---")
                
                temp_page = await context.new_page()
                await temp_page.route("**/*", block_resources)
                
                try:
                    await temp_page.goto(m_url, wait_until="domcontentloaded", timeout=60000)
                    model_items = await temp_page.query_selector_all(".model_item")
                    
                    process_tasks = []
                    for item in model_items:
                        m_input = await item.query_selector("input.model-checkbox")
                        identifier = await m_input.get_attribute("value") if m_input else None
                        m_link = await item.query_selector("a.c-bike_image")
                        href = await m_link.get_attribute("href") if m_link else None
                        
                        if identifier and href:
                            bike_model_id = model_ident_cache.get(identifier)
                            if bike_model_id:
                                process_tasks.append(
                                    process_model_page(context, base_url, href, bike_model_id, site_id, shop_cache, known_urls)
                                )

                    # メーカー内の車種を並列実行
                    if process_tasks:
                        await asyncio.gather(*process_tasks)
                        
                except Exception as e:
                    print(f"  メーカーページ巡回エラー ({m['name']}): {e}")
                finally:
                    await temp_page.close()
                    # サーバーへの配慮
                    await asyncio.sleep(random.uniform(1, 2))

            print("\nBDS出品情報の同期が完了しました。")

        finally:
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())