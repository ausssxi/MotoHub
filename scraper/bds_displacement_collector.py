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
    id = Column(BigInteger, primary_key=True)
    name = Column(String(100), unique=True)

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    manufacturer_id = Column(BigInteger)
    name = Column(String(255), nullable=False, unique=True)
    category = Column(String(50), nullable=True)
    displacement = Column(Integer, nullable=True) # 排気量カラム
    created_at = Column(DateTime, default=datetime.datetime.now)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

class BikeModelIdentifier(Base):
    __tablename__ = "bike_model_identifiers"
    id = Column(BigInteger, primary_key=True)
    bike_model_id = Column(BigInteger, ForeignKey("bike_models.id"))
    site_id = Column(BigInteger)
    identifier = Column(String(100))

async def collect_displacement():
    async with async_playwright() as p:
        print("BDS排気量コレクターを起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            viewport={'width': 1920, 'height': 1080}
        )
        page = await context.new_page()

        base_url = "https://www.bds-bikesensor.net"
        db = SessionLocal()
        
        # BDSのサイトIDを特定
        bds_site = db.query(Site).filter(Site.name == "BDS").first()
        if not bds_site:
            print("エラー: sitesテーブルに 'BDS' が登録されていません。")
            return
        site_id = bds_site.id

        # 直接定義したメーカーリスト
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

        try:
            for m in maker_list_raw:
                m_url = f"{base_url}/bike/maker/{m['slug']}"
                print(f"\n--- {m['name']} のメーカーページを解析中: {m_url} ---")
                
                try:
                    await page.goto(m_url, wait_until="domcontentloaded", timeout=60000)
                    model_items = await page.query_selector_all(".model_item")
                    
                    for item in model_items:
                        # 車種リンク情報の抽出
                        m_link = await item.query_selector("a.c-bike_image")
                        if not m_link: continue
                        
                        model_name = (await m_link.get_attribute("title") or "").strip()
                        href = await m_link.get_attribute("href")
                        
                        if not model_name or not href: continue

                        # DB確認：既に排気量がある場合はスキップ
                        model_record = db.query(BikeModel).filter(BikeModel.name == model_name).first()
                        if model_record and model_record.displacement and model_record.displacement > 0:
                            # print(f"  [SKIP] {model_name} は既に排気量({model_record.displacement}cc)が登録済みです。")
                            continue

                        # 各車種の車両一覧ページへ侵入
                        search_page_url = base_url + href if href.startswith('/') else href
                        print(f"  >> {model_name} の詳細ページを解析中...")
                        
                        try:
                            # 2つ目のページオブジェクトを作って効率化
                            sub_page = await context.new_page()
                            await sub_page.goto(search_page_url, wait_until="networkidle", timeout=60000)
                            
                            # 排気量ブロックを探す
                            # スマホ版・PC版の両方に対応できるよう汎用的なセレクターを使用
                            status_cols = await sub_page.query_selector_all(".c-search_status_col")
                            disp_val = None
                            
                            for col in status_cols:
                                head_el = await col.query_selector(".c-search_status_head")
                                if head_el and "排気量" in (await head_el.inner_text()):
                                    # 値が入っている p.c-search_status_title01 を取得
                                    val_el = await col.query_selector(".c-search_status_title01")
                                    if val_el:
                                        raw_val = (await val_el.inner_text()).strip()
                                        # 数値のみ抽出
                                        match = re.search(r'(\d+)', raw_val)
                                        if match:
                                            disp_val = int(match.group(1))
                                            break # 排気量が見つかったらループ終了
                            
                            await sub_page.close()

                            if disp_val:
                                if model_record:
                                    model_record.displacement = disp_val
                                    db.commit()
                                    print(f"    [更新成功] {model_name}: {disp_val}cc")
                                # １回でも取得したら（この車種の解析が終わったら）、次の車種に移る
                            else:
                                print(f"    [警告] {model_name} の排気量が見つかりませんでした（在庫なし等）。")

                        except Exception as sub_e:
                            print(f"    [エラー] 車種詳細取得中: {sub_e}")
                            if 'sub_page' in locals(): await sub_page.close()

                    await asyncio.sleep(0.5)

                except Exception as e:
                    print(f"  メーカーページ解析エラー ({m['name']}): {e}")

            print("\nBDS排気量データの更新が完了しました。")

        except Exception as e:
            print(f"致命的なエラー: {e}")
        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect_displacement())