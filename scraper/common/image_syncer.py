import os
import asyncio
import httpx
import mimetypes
import random
import sys
import logging
from sqlalchemy import create_engine, Column, BigInteger, JSON, Text, String, or_
from sqlalchemy.orm import DeclarativeBase, sessionmaker
from dotenv import load_dotenv

# ==========================================
# 1. 環境設定 & データベース定義
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
env_path = os.path.join(current_dir, '..', '.env')
load_dotenv(dotenv_path=env_path)

def get_env_or_exit(key, default=None):
    val = os.getenv(key, default)
    if val is None:
        print(f"致命的エラー: 必須の環境変数 '{key}' が設定されていません。")
        sys.exit(1)
    return val

DATABASE_URL = f"mysql+pymysql://{get_env_or_exit('DB_USERNAME')}:{get_env_or_exit('DB_PASSWORD')}@{get_env_or_exit('DB_HOST', 'db')}:{get_env_or_exit('DB_PORT', '3306')}/{get_env_or_exit('DB_DATABASE')}"

engine = create_engine(DATABASE_URL, pool_pre_ping=True)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

# ストレージパスの解決
DEFAULT_STORAGE_PATH = os.path.abspath(os.path.join(current_dir, "../backend/storage/app/public"))
STORAGE_BASE_PATH = os.getenv("IMAGE_STORAGE_PATH", DEFAULT_STORAGE_PATH)
if STORAGE_BASE_PATH.endswith(("/listings", "/listings/")):
    STORAGE_BASE_PATH = os.path.dirname(STORAGE_BASE_PATH.rstrip('/'))

class Base(DeclarativeBase): pass

class Listing(Base):
    __tablename__ = "listings"
    id = Column(BigInteger, primary_key=True)
    site_id = Column(BigInteger)
    image_urls = Column(JSON)
    local_image_paths = Column(JSON, nullable=True)

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True)
    image_url = Column(Text)
    local_image_path = Column(JSON, nullable=True)

class Manufacturer(Base):
    __tablename__ = "manufacturers"
    id = Column(BigInteger, primary_key=True)
    logo_url = Column(String(255))
    local_logo_path = Column(String(255), nullable=True)

class Shop(Base):
    __tablename__ = "shops"
    id = Column(BigInteger, primary_key=True)
    image_url = Column(String(255))
    local_image_path = Column(String(255), nullable=True)

# ==========================================
# 2. コアロジック (並列処理対応)
# ==========================================

# 同時ダウンロード数を制限 (相手サーバーへの負荷と自マシンのリソースの兼ね合い)
MAX_CONCURRENT_DOWNLOADS = 10
semaphore = asyncio.Semaphore(MAX_CONCURRENT_DOWNLOADS)

async def download_image(client, url, sub_dir, retries=3):
    """リトライ機能付き並列ダウンロード"""
    if not url or not str(url).startswith("http"):
        return None
    
    async with semaphore:
        for attempt in range(retries):
            try:
                # 負荷分散のための微小なウェイト
                await asyncio.sleep(random.uniform(0.1, 0.3))
                
                headers = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"}
                resp = await client.get(url, headers=headers, timeout=20.0)
                
                if resp.status_code == 200:
                    content_type = resp.headers.get("Content-Type", "")
                    ext = mimetypes.guess_extension(content_type.split(';')[0]) or ".jpg"
                    
                    save_rel_path = sub_dir + ext
                    abs_path = os.path.join(STORAGE_BASE_PATH, save_rel_path)
                    
                    os.makedirs(os.path.dirname(abs_path), exist_ok=True)
                    with open(abs_path, "wb") as f:
                        f.write(resp.content)
                    return save_rel_path
                
                if resp.status_code == 404:
                    return None # 存在しない場合はリトライしない
                    
            except Exception as e:
                if attempt == retries - 1:
                    logging.error(f"Failed to download {url} after {retries} attempts: {e}")
                await asyncio.sleep(2 ** attempt) # 指数バックオフ
        return None

async def process_item_images(client, item, table_name, url_attr):
    """1レコードに紐づく全ての画像を処理"""
    urls = getattr(item, url_attr)
    if not urls:
        return []

    url_list = urls if isinstance(urls, list) else [urls]
    shard = str(item.id % 100).zfill(2)
    
    # サイト・テーブル別のディレクトリ構造決定
    if table_name == "listings":
        site_map = {1: "goobike", 2: "bds", 3: "webike"}
        site_name = site_map.get(item.site_id, "other")
        base_dir = f"listings/{site_name}/{shard}/{item.id}"
    elif table_name == "manufacturers":
        base_dir = f"manufacturers/{item.id}"
    else:
        base_dir = f"{table_name}/{shard}/{item.id}"

    # 全画像を並列でスケジュール
    tasks = [download_image(client, url, f"{base_dir}/{i}") for i, url in enumerate(url_list)]
    results = await asyncio.gather(*tasks)
    
    # 成功したものだけをフィルタリング
    return [r for r in results if r]

# ==========================================
# 3. 同期エンジン
# ==========================================

async def sync_table(target_class, table_label, url_field, local_field):
    """指定テーブルの未同期画像をバッチ処理"""
    batch_size = 50
    
    async with httpx.AsyncClient(follow_redirects=True, limits=httpx.Limits(max_connections=20)) as client:
        while True:
            db = SessionLocal()
            try:
                # local_field が None のものを探す
                query = db.query(target_class).filter(
                    getattr(target_class, url_field) != None,
                    getattr(target_class, local_field) == None
                )
                items = query.limit(batch_size).all()

                if not items:
                    print(f"[{table_label}] 同期完了。")
                    break

                print(f"[{table_label}] {len(items)} 件の画像を並列処理中...")
                
                # レコードごとの並列処理タスクを作成
                for item in items:
                    paths = await process_item_images(client, item, table_label, url_field)
                    
                    # カラム型に合わせて保存形式を切り替え
                    column_type = getattr(target_class, local_field).type
                    if isinstance(column_type, String) and not isinstance(column_type, JSON):
                        setattr(item, local_field, paths[0] if paths else "")
                    else:
                        setattr(item, local_field, paths)
                    
                    db.commit() # 進捗を1件ずつ確定（クラッシュ対策）
                
                print(f"  -> バッチ処理完了")
                await asyncio.sleep(0.2)

            except Exception as e:
                print(f"[{table_label}] エラー: {e}")
                db.rollback()
                await asyncio.sleep(5)
            finally:
                db.close()

async def main():
    logging.basicConfig(level=logging.INFO)
    print(f"--- 画像同期プロセッサ起動 ---")
    print(f"Root: {STORAGE_BASE_PATH}")
    
    os.makedirs(STORAGE_BASE_PATH, exist_ok=True)
    
    # 実行順序（依存の少ないものから）
    await sync_table(Manufacturer, "manufacturers", "logo_url", "local_logo_path")
    await sync_table(Shop, "shops", "image_url", "local_image_path")
    await sync_table(BikeModel, "bike_models", "image_url", "local_image_path")
    await sync_table(Listing, "listings", "image_urls", "local_image_paths")

if __name__ == "__main__":
    asyncio.run(main())