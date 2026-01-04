import asyncio
import os
import datetime
import re
import unicodedata
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, func, or_
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# 環境変数の読み込み (フォルダ階層に合わせて修正)
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

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    name = Column(String(255), nullable=False, unique=True)
    displacement = Column(Integer, nullable=True)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

def robust_normalize(text):
    """文字のゆれを徹底的に排除する"""
    if not text:
        return ""
    text = unicodedata.normalize('NFKC', text)
    text = text.upper()
    text = re.sub(r'[ー－―—‐-]', '-', text)
    return text.strip()

# 詳細ページへの同時接続数を制限（一度に8ページ程度が効率的）
MAX_CONCURRENT_DETAIL_PAGES = 8
semaphore = asyncio.Semaphore(MAX_CONCURRENT_DETAIL_PAGES)

async def block_resources(route):
    """画像、CSS、フォントなどの不要なリソースを遮断"""
    if route.request.resource_type in ["image", "media", "font", "stylesheet"]:
        await route.abort()
    else:
        await route.continue_()

async def fetch_model_displacement(context, model_id, model_name, url):
    """車両個別ページから排気量を取得しDBを更新する並列タスク"""
    async with semaphore:
        db = SessionLocal()
        page = await context.new_page()
        # リソース制限の適用
        await page.route("**/*", block_resources)
        
        try:
            # URLが相対パスの場合は結合
            target_url = url if url.startswith('http') else f"https://www.bds-bikesensor.net{url if url.startswith('/') else '/' + url}"
            
            # domcontentloaded で十分なので早めに切り上げる
            await page.goto(target_url, wait_until="domcontentloaded", timeout=30000)
            
            # 排気量情報の抽出 (c-search_status_col 内にある)
            status_cols = await page.query_selector_all(".c-search_status_col")
            disp_val = None
            for col in status_cols:
                head = await col.query_selector(".c-search_status_head")
                if head and "排気量" in (await head.inner_text()):
                    val_el = await col.query_selector(".c-search_status_title01")
                    if val_el:
                        match = re.search(r'(\d+)', await val_el.inner_text())
                        if match:
                            disp_val = int(match.group(1))
                            break
            
            if disp_val:
                model = db.query(BikeModel).get(model_id)
                if model:
                    model.displacement = disp_val
                    db.commit()
                    print(f"    [更新] {model_name} -> {disp_val}cc")
        except Exception:
            # 個別エラーはログを出さずスキップ
            pass
        finally:
            db.close()
            await page.close()

async def process_manufacturer(context, m_info, model_cache):
    """メーカー一覧ページを解析し、詳細取得が必要な車種のタスクを発行する"""
    page = await context.new_page()
    await page.route("**/*", block_resources)
    
    m_url = f"https://www.bds-bikesensor.net/bike/maker/{m_info['slug']}"
    print(f"\n--- {m_info['name']} の巡回開始 ---")
    
    try:
        await page.goto(m_url, wait_until="domcontentloaded", timeout=60000)
        model_items = await page.query_selector_all(".model_item")
        
        detail_tasks = []
        for item in model_items:
            m_link = await item.query_selector("a.c-bike_image")
            if not m_link: continue
            
            raw_title = (await m_link.get_attribute("title") or "").strip()
            norm_title = robust_normalize(raw_title)
            href = await m_link.get_attribute("href")
            
            if not norm_title or not href: continue
            
            # キャッシュ（排気量未設定の車種名）に存在するかチェック
            if norm_title in model_cache:
                m_data = model_cache[norm_title]
                # 詳細ページ取得タスクを生成（並列実行用）
                detail_tasks.append(fetch_model_displacement(context, m_data['id'], m_data['name'], href))
        
        # メーカー内の全車種を並列で取得・更新
        if detail_tasks:
            print(f"  >> {len(detail_tasks)} 件の車種詳細を取得中...")
            await asyncio.gather(*detail_tasks)
            
    except Exception as e:
        print(f"  [エラー] {m_info['name']} の一覧取得に失敗: {e}")
    finally:
        await page.close()

async def collect():
    async with async_playwright() as p:
        print("BDS排気量コレクター（超高速版）を起動しています...")
        browser = await p.chromium.launch(headless=True)
        # 一度に多数のページを扱うためコンテキストを生成
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        
        db = SessionLocal()
        # 1. 排気量が未設定（または0）の車種を事前にメモリへ読み込む
        print("未設定モデルのキャッシュを構築中...")
        all_models = db.query(BikeModel).filter(
            or_(BikeModel.displacement == None, BikeModel.displacement == 0)
        ).all()
        
        # 正規化した名前をキー、IDと元の名前を値にした辞書
        model_cache = {robust_normalize(m.name): {"id": m.id, "name": m.name} for m in all_models}
        db.close()
        
        if not model_cache:
            print("更新が必要な車種はありません。")
            await browser.close()
            return

        # 2. フルメーカーリストを巡回
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

        # メーカーごとに一覧を取得し、必要な車種が見つかったら並列で詳細ページを開く
        for m in maker_list:
            await process_manufacturer(context, m, model_cache)

        print("\nすべての排気量同期が完了しました。")
        await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())