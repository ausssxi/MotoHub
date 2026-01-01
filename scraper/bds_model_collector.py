import asyncio
import os
import datetime
import re
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, ForeignKey, UniqueConstraint
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

class Site(Base):
    __tablename__ = "sites"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(50), unique=True)

class Manufacturer(Base):
    __tablename__ = "manufacturers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    name = Column(String(100), unique=True, nullable=False)

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    manufacturer_id = Column(BigInteger, nullable=False)
    name = Column(String(255), nullable=False, unique=True)
    category = Column(String(50), nullable=True)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

class BikeModelIdentifier(Base):
    __tablename__ = "bike_model_identifiers"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    bike_model_id = Column(BigInteger, ForeignKey("bike_models.id", ondelete="CASCADE"), nullable=False)
    site_id = Column(BigInteger, ForeignKey("sites.id", ondelete="CASCADE"), nullable=False)
    identifier = Column(String(100), nullable=False)
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)
    
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_site_identifier_uc'),)

async def collect_bds_data():
    async with async_playwright() as p:
        print("BDSブラウザを起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
            viewport={'width': 1920, 'height': 1080}
        )
        page = await context.new_page()

        base_url = "https://www.bds-bikesensor.net"
        db = SessionLocal()
        
        # 1. サイトIDの取得
        bds_site = db.query(Site).filter(Site.name == "BDS").first()
        if not bds_site:
            print("エラー: sitesテーブルに 'BDS' が登録されていません。")
            return
        site_id = bds_site.id

        # --- 直接定義したメーカーリスト ---
        # HTMLの value と表示名をマッピング
        maker_list_raw = [
            {"slug": "honda", "name": "ホンダ"},
            {"slug": "suzuki", "name": "スズキ"},
            {"slug": "yamaha", "name": "ヤマハ"},
            {"slug": "kawasaki", "name": "カワサキ"},
            {"slug": "daihatsu", "name": "ダイハツ"},
            {"slug": "bridgestone", "name": "ブリジストン"},
            {"slug": "meguro", "name": "メグロ"},
            {"slug": "rodeo", "name": "ロデオ"},
            {"slug": "plot", "name": "プロト"},
            {"slug": "bmw", "name": "BMW"},
            {"slug": "ktm", "name": "KTM"},
            {"slug": "aprilia", "name": "アプリリア"},
            {"slug": "mv_agusta", "name": "MVアグスタ"},
            {"slug": "gilera", "name": "ジレラ"},
            {"slug": "ducati", "name": "ドゥカティ"},
            {"slug": "triumph", "name": "トライアンフ"},
            {"slug": "norton", "name": "ノートン"},
            {"slug": "harley_davidson", "name": "ハーレーダビッドソン"},
            {"slug": "husqvarna", "name": "ハスクバーナ"},
            {"slug": "bimota", "name": "ビモータ"},
            {"slug": "buell", "name": "ビューエル"},
            {"slug": "vespa", "name": "ベスパ"},
            {"slug": "moto_guzzi", "name": "モトグッツィ"},
            {"slug": "royal_enfield", "name": "ロイヤルエンフィールド"},
            {"slug": "daelim", "name": "DAELIM"},
            {"slug": "gg", "name": "GG"},
            {"slug": "pgo", "name": "PGO"},
            {"slug": "sym", "name": "SYM"},
            {"slug": "italjet", "name": "イタルジェット"},
            {"slug": "gasgas", "name": "ガスガス"},
            {"slug": "kymco", "name": "キムコ"},
            {"slug": "krauser", "name": "クラウザー"},
            {"slug": "sachs", "name": "ザックス"},
            {"slug": "derbi", "name": "デルビ"},
            {"slug": "tomos", "name": "トモス"},
            {"slug": "piaggio", "name": "ピアジオ"},
            {"slug": "bsa", "name": "ビーエスエー"},
            {"slug": "fantic", "name": "ファンティック"},
            {"slug": "peugeot", "name": "プジョー"},
            {"slug": "beta", "name": "ベータ"},
            {"slug": "benelli", "name": "ベネリ"},
            {"slug": "magni", "name": "マーニ"},
            {"slug": "moto_morini", "name": "モトモリーニ"},
            {"slug": "mondial", "name": "モンディアル"},
            {"slug": "montesa", "name": "モンテッサ"},
            {"slug": "lambretta", "name": "ランブレッタ"},
            {"slug": "adiva", "name": "アディバ"},
            {"slug": "megelli", "name": "メガリ"},
            {"slug": "indian", "name": "インディアン"},
            {"slug": "gpx", "name": "GPX"},
            {"slug": "phoenix", "name": "PHOENIX"},
            {"slug": "leonart", "name": "レオンアート"},
            {"slug": "brp", "name": "BRP"},
            {"slug": "brixton", "name": "BRIXTON"},
            {"slug": "mutt", "name": "MUTT"},
        ]

        maker_targets = []
        for m in maker_list_raw:
            maker_targets.append({
                "name": m["name"],
                "url": f"{base_url}/bike/maker/{m['slug']}"
            })

        print(f"定義済みメーカーリストに基づき、合計 {len(maker_targets)} 件のメーカーを巡回します。")

        try:
            # --- 各メーカーページを巡回して車種を取得 ---
            for m_info in maker_targets:
                print(f"\n--- {m_info['name']} の車種を解析中 ---")
                
                # DBからメーカー情報を取得 (Seeder等ですでに入っている前提)
                m_record = db.query(Manufacturer).filter(Manufacturer.name.like(f"%{m_info['name']}%")).first()
                if not m_record:
                    print(f"  警告: メーカー '{m_info['name']}' がDBに見つかりません。新規作成します。")
                    m_record = Manufacturer(name=m_info['name'])
                    db.add(m_record)
                    db.flush()

                try:
                    await page.goto(m_info['url'], wait_until="networkidle", timeout=60000)
                    
                    # 各車種ブロックの抽出
                    model_blocks = await page.query_selector_all(".c-search_name_block_wrap")
                    
                    for block in model_blocks:
                        # 車種識別番号(value)
                        m_input = await block.query_selector("input[type='checkbox']")
                        identifier_val = await m_input.get_attribute("value") if m_input else None
                        
                        # 車種名(title)
                        m_link = await block.query_selector("a.c-bike_image")
                        model_name = (await m_link.get_attribute("title") if m_link else "").strip()

                        if not model_name or not identifier_val:
                            continue

                        # 車種マスタの確認・登録
                        existing_model = db.query(BikeModel).filter(BikeModel.name == model_name).first()
                        if not existing_model:
                            existing_model = BikeModel(
                                name=model_name,
                                manufacturer_id=m_record.id,
                                category="不明"
                            )
                            db.add(existing_model)
                            db.flush() 
                            print(f"  [新モデル追加] {model_name}")
                        
                        # BDSサイト固有の識別番号を保存
                        existing_idnt = db.query(BikeModelIdentifier).filter(
                            BikeModelIdentifier.site_id == site_id,
                            BikeModelIdentifier.identifier == identifier_val
                        ).first()
                        
                        if not existing_idnt:
                            new_idnt = BikeModelIdentifier(
                                bike_model_id=existing_model.id,
                                site_id=site_id,
                                identifier=identifier_val
                            )
                            db.add(new_idnt)
                            print(f"    [認識番号登録] {model_name} -> {identifier_val}")
                    
                    db.commit()
                    await asyncio.sleep(0.5)

                except Exception as e:
                    print(f"  エラー ({m_info['name']}): {e}")
                    db.rollback()

            # --- カテゴリー(ジャンル)の同期 ---
            print("\n--- バイクスタイル(カテゴリー)の同期を開始します ---")
            await page.goto(f"{base_url}/bike", wait_until="networkidle")
            
            genre_elements = await page.query_selector_all(".narrow_down.category .c-search_name_block_wrap")
            
            genre_list = []
            for ge in genre_elements:
                g_input = await ge.query_selector("input")
                g_val = await g_input.get_attribute("value")
                g_label = await ge.query_selector(".c-search_name")
                g_name = (await g_label.inner_text()).strip() if g_label else ""
                
                if g_name and g_val:
                    genre_list.append({
                        "name": g_name, 
                        "url": f"{base_url}/bike?categories%5B%5D={g_val}"
                    })

            for genre in genre_list:
                print(f"カテゴリー '{genre['name']}' を同期中...")
                try:
                    await page.goto(genre['url'], wait_until="networkidle", timeout=60000)
                    bike_links = await page.query_selector_all(".c-search_content a.c-bike_image")
                    for bl in bike_links:
                        m_name = (await bl.get_attribute("title") or "").strip()
                        if not m_name: continue
                        targets = db.query(BikeModel).filter(BikeModel.name == m_name).all()
                        for t in targets:
                            if t.category != genre['name']:
                                t.category = genre['name']
                    db.commit()
                    await asyncio.sleep(0.5)
                except Exception as e:
                    print(f"ジャンル解析エラー: {e}")
                    db.rollback()

            print("\nBDSデータの全同期が完了しました。")

        except Exception as e:
            print(f"致命的エラー: {e}")
        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect_bds_data())