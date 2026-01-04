import asyncio
import os
import datetime
import re
import unicodedata
import sys
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, func, or_
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# 1. 環境変数の読み込み
# 現在のファイル位置 (scraper/bds/) から見て、2つ上の階層 (scraper/) にある .env を探す
current_dir = os.path.dirname(os.path.abspath(__file__))
env_path = os.path.join(current_dir, '..', '..', '.env')
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

# 詳細ページへの同時接続数を制限
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
        await page.route("**/*", block_resources)
        
        try:
            target_url = url if url.startswith('http') else f"https://www.bds-bikesensor.net{url if url.startswith('/') else '/' + url}"
            await page.goto(target_url, wait_until="domcontentloaded", timeout=30000)
            
            # 排気量情報の抽出
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
            
            if norm_title in model_cache:
                m_data = model_cache[norm_title]
                detail_tasks.append(fetch_model_displacement(context, m_data['id'], m_data['name'], href))
        
        if detail_tasks:
            print(f"  >> {len(detail_tasks)} 件の車種詳細を取得中...")
            await asyncio.gather(*detail_tasks)
            
    except Exception as e:
        print(f"  [エラー] {m_info['name']} の一覧取得に失敗: {e}")
    finally:
        await page.close()

async def collect():
    async with async_playwright() as p:
        print("BDS排気量コレクター（セキュア版）を起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        
        db = SessionLocal()
        print("未設定モデルのキャッシュを構築中...")
        all_models = db.query(BikeModel).filter(
            or_(BikeModel.displacement == None, BikeModel.displacement == 0)
        ).all()
        
        model_cache = {robust_normalize(m.name): {"id": m.id, "name": m.name} for m in all_models}
        db.close()
        
        if not model_cache:
            print("更新が必要な車種はありません。")
            await browser.close()
            return

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

        for m in maker_list:
            await process_manufacturer(context, m, model_cache)

        print("\nすべての排気量同期が完了しました。")
        await browser.close()

if __name__ == "__main__":
    asyncio.run(collect())