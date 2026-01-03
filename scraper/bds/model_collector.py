import asyncio
import os
import datetime
import re
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, ForeignKey, UniqueConstraint, or_
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
    displacement = Column(Integer, nullable=True)
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

# 並列実行の設定（一度に5ページ）
MAX_CONCURRENT_PAGES = 5
semaphore = asyncio.Semaphore(MAX_CONCURRENT_PAGES)

async def block_resources(route):
    """不要なリソースの読み込みを遮断"""
    if route.request.resource_type in ["image", "media", "font", "stylesheet"]:
        await route.abort()
    else:
        await route.continue_()

async def process_maker(context, target, site_id, existing_models, manufacturer_cache):
    """1つのメーカーの車種情報を収集するタスク"""
    async with semaphore:
        db = SessionLocal()
        page = await context.new_page()
        await page.route("**/*", block_resources)

        try:
            print(f"  [開始] {target['name']}")
            await page.goto(target['url'], wait_until="domcontentloaded", timeout=60000)
            
            # 車種ブロックの取得
            model_blocks = await page.query_selector_all(".model_item")
            m_record_id = manufacturer_cache.get(target['name'])
            
            if not m_record_id:
                return

            new_models_count = 0
            for block in model_blocks:
                m_input = await block.query_selector("input.model-checkbox")
                identifier_val = await m_input.get_attribute("value") if m_input else None
                m_link = await block.query_selector("a.c-bike_image")
                model_name = (await m_link.get_attribute("title") if m_link else "").strip()

                if not model_name or not identifier_val: continue

                # キャッシュで重複チェック
                model_id = existing_models.get(model_name)
                
                # キャッシュにない場合、並列処理による重複を防ぐためDBを再確認
                if not model_id:
                    # DBを直接確認
                    db_model = db.query(BikeModel).filter(BikeModel.name == model_name).first()
                    if db_model:
                        model_id = db_model.id
                        existing_models[model_name] = model_id
                    else:
                        # 本当にない場合のみ登録を試みる
                        try:
                            new_model = BikeModel(
                                name=model_name,
                                manufacturer_id=m_record_id,
                                category="不明",
                                displacement=None
                            )
                            db.add(new_model)
                            db.flush()
                            model_id = new_model.id
                            existing_models[model_name] = model_id
                            new_models_count += 1
                        except IntegrityError:
                            # タッチの差で他タスクが登録した場合の救済
                            db.rollback()
                            db_model = db.query(BikeModel).filter(BikeModel.name == model_name).first()
                            if db_model:
                                model_id = db_model.id
                                existing_models[model_name] = model_id

                # 識別番号の登録
                if model_id and identifier_val:
                    exists = db.query(BikeModelIdentifier).filter(
                        BikeModelIdentifier.site_id == site_id,
                        BikeModelIdentifier.identifier == identifier_val
                    ).first()
                    
                    if not exists:
                        db.add(BikeModelIdentifier(
                            bike_model_id=model_id,
                            site_id=site_id,
                            identifier=identifier_val
                        ))
            
            db.commit()
            if new_models_count > 0:
                print(f"  [完了] {target['name']}: {new_models_count}件の新車種を登録")
        except Exception as e:
            print(f"  [エラー] {target['name']}: {e}")
            db.rollback()
        finally:
            db.close()
            await page.close()

async def collect():
    async with async_playwright() as p:
        print("BDSモデルコレクター（高速・フルリスト版）を起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        
        db = SessionLocal()
        bds_site = db.query(Site).filter(Site.name == "BDS").first()
        if not bds_site:
            print("エラー: sitesテーブルに 'BDS' が登録されていません。")
            return
        site_id = bds_site.id

        # インメモリキャッシュの構築
        print("キャッシュを構築中...")
        existing_models = {m.name: m.id for m in db.query(BikeModel).all()}
        manufacturer_cache = {m.name: m.id for m in db.query(Manufacturer).all()}

        # 巡回するメーカーのフルリスト
        maker_list_raw = [
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

        # ターゲットURLの生成とメーカーマスタの事前チェック
        maker_targets = []
        for m in maker_list_raw:
            # キャッシュにあるか確認
            m_id = manufacturer_cache.get(m['name'])
            
            if not m_id:
                # キャッシュになくても、DBに直接問い合わせて再確認（BMWエラー対策）
                m_record = db.query(Manufacturer).filter(Manufacturer.name == m['name']).first()
                if m_record:
                    m_id = m_record.id
                    manufacturer_cache[m['name']] = m_id
                else:
                    # 本当に存在しない場合のみ追加
                    try:
                        m_record = Manufacturer(name=m['name'])
                        db.add(m_record)
                        db.flush() # IDを確定
                        m_id = m_record.id
                        manufacturer_cache[m['name']] = m_id
                    except IntegrityError:
                        db.rollback()
                        # 他のプロセスが先に登録した可能性を考慮し、再取得
                        m_record = db.query(Manufacturer).filter(Manufacturer.name == m['name']).first()
                        if m_record:
                            m_id = m_record.id
                            manufacturer_cache[m['name']] = m_id
            
            if m_id:
                maker_targets.append({
                    "name": m['name'],
                    "url": f"https://www.bds-bikesensor.net/bike/maker/{m['slug']}"
                })
        
        db.commit()

        # 並列実行の開始
        print(f"\n並列実行を開始します（最大 {MAX_CONCURRENT_PAGES} 並列）...")
        tasks = [
            process_maker(context, target, site_id, existing_models, manufacturer_cache)
            for target in maker_targets
        ]
        
        await asyncio.gather(*tasks)

        print("\nすべての同期が完了しました。")
        db.close()
        await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())