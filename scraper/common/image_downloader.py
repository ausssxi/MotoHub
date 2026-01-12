import os
import asyncio
import httpx
import mimetypes
import random
import sys
from sqlalchemy import create_engine, Column, BigInteger, JSON, Text, String
from sqlalchemy.orm import DeclarativeBase, sessionmaker
from dotenv import load_dotenv

# ==========================================
# 1. 環境設定 & データベース定義
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
env_path = os.path.join(current_dir, '..', '..', '.env')
load_dotenv(dotenv_path=env_path)

def get_env_or_exit(key, default=None, required=True):
    val = os.getenv(key, default)
    if required and val is None:
        print(f"致命的エラー: 必須の環境変数 '{key}' が設定されていません。")
        sys.exit(1)
    return val

DATABASE_URL = f"mysql+pymysql://{get_env_or_exit('DB_USERNAME')}:{get_env_or_exit('DB_PASSWORD')}@{get_env_or_exit('DB_HOST', 'db')}:{get_env_or_exit('DB_PORT', '3306')}/{get_env_or_exit('DB_DATABASE')}"

engine = create_engine(DATABASE_URL)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

# --- 保存先ベースパスの修正ロジック ---
# デフォルトは backend/storage/app/public
DEFAULT_STORAGE_PATH = os.path.abspath(os.path.join(current_dir, "../../backend/storage/app/public"))
raw_base_path = os.getenv("IMAGE_STORAGE_PATH", DEFAULT_STORAGE_PATH)

# もしベースパスが 'listings' で終わっていたら、その親（public）をベースにする
# これにより、listings/shops のような入れ子を防ぎ、public/listings と public/shops を並列にします
if raw_base_path.endswith("/listings") or raw_base_path.endswith("/listings/"):
    STORAGE_BASE_PATH = os.path.dirname(raw_base_path.rstrip('/'))
else:
    STORAGE_BASE_PATH = raw_base_path

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
# 2. ダウンロード・コアロジック
# ==========================================

async def download_image(client, url, sub_dir):
    """画像をダウンロードして保存し、相対パスを返す"""
    if not url or not url.startswith("http"):
        return None
    
    try:
        headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        }
        await asyncio.sleep(random.uniform(0.05, 0.2))
        
        resp = await client.get(url, headers=headers, timeout=15.0)
        if resp.status_code != 200:
            return None

        content_type = resp.headers.get("Content-Type", "")
        ext = mimetypes.guess_extension(content_type.split(';')[0]) or ".jpg"
        
        # 保存パスの構築（STORAGE_BASE_PATHは常に各カテゴリの親フォルダを指す）
        save_rel_path = sub_dir + ext
        abs_path = os.path.join(STORAGE_BASE_PATH, save_rel_path)
        
        os.makedirs(os.path.dirname(abs_path), exist_ok=True)
        
        with open(abs_path, "wb") as f:
            f.write(resp.content)
            
        return save_rel_path

    except Exception as e:
        print(f"      Download Error ({url}): {e}")
    return None

async def process_generic(client, item, table_name, url_attr):
    """汎用的なテーブル別画像処理ロジック"""
    urls = getattr(item, url_attr)
    if not urls:
        return []

    url_list = urls if isinstance(urls, list) else [urls]
    shard = str(item.id % 100).zfill(2)
    
    # フォルダ構成を明示的に定義
    if table_name == "listings":
        site_name = "goobike" if item.site_id == 1 else "bds"
        base_dir = f"listings/{site_name}/{shard}/{item.id}"
    elif table_name == "manufacturers":
        base_dir = f"manufacturers/{item.id}"
    else:
        # shops, bike_models
        base_dir = f"{table_name}/{shard}/{item.id}"

    downloaded_paths = []
    for i, url in enumerate(url_list):
        sub_dir = f"{base_dir}/{i}"
        path = await download_image(client, url, sub_dir)
        if path:
            downloaded_paths.append(path)
    
    return downloaded_paths

# ==========================================
# 3. 実行制御ブロック
# ==========================================

async def run_sync_for_table(target_class, table_label, url_field, local_field):
    """指定されたテーブルの未処理画像をバッチ処理"""
    batch_size = 50
    total_processed = 0

    while True:
        db = SessionLocal()
        try:
            query = db.query(target_class).filter(
                getattr(target_class, url_field) != None,
                getattr(target_class, local_field) == None
            )
            items = query.limit(batch_size).all()

            if not items:
                print(f"[{table_label}] 同期完了。")
                break

            print(f"[{table_label}] {len(items)} 件の処理中...")
            async with httpx.AsyncClient(follow_redirects=True) as client:
                for item in items:
                    paths = await process_generic(client, item, table_label, url_field)
                    
                    if paths:
                        column_type = getattr(target_class, local_field).type
                        if isinstance(column_type, String) and not isinstance(column_type, JSON):
                            setattr(item, local_field, paths[0])
                        else:
                            setattr(item, local_field, paths)
                    else:
                        column_type = getattr(target_class, local_field).type
                        setattr(item, local_field, [] if isinstance(column_type, JSON) else "")
                    
                    db.commit()
                    total_processed += 1
            
            print(f"  -> {total_processed} 件処理済み")
            await asyncio.sleep(0.5)

        except Exception as e:
            print(f"[{table_label}] エラー: {e}")
            db.rollback()
            await asyncio.sleep(5)
        finally:
            db.close()

async def main():
    # ログ出力で現在のベースパスを明示
    print(f"--- 画像同期ツール起動 ---")
    print(f"保存先ルート: {STORAGE_BASE_PATH}")
    print(f"各フォルダ構成例:")
    print(f"  - {STORAGE_BASE_PATH}/listings/...")
    print(f"  - {STORAGE_BASE_PATH}/shops/...")
    print(f"  - {STORAGE_BASE_PATH}/manufacturers/...")
    print(f"--------------------------")
    
    if not os.path.exists(STORAGE_BASE_PATH):
        os.makedirs(STORAGE_BASE_PATH, exist_ok=True)
    
    # カテゴリごとに実行（第2引数がベースパス直下のフォルダ名になります）
    await run_sync_for_table(Manufacturer, "manufacturers", "logo_url", "local_logo_path")
    await run_sync_for_table(Shop, "shops", "image_url", "local_image_path")
    await run_sync_for_table(BikeModel, "bike_models", "image_url", "local_image_path")
    await run_sync_for_table(Listing, "listings", "image_urls", "local_image_paths")

if __name__ == "__main__":
    asyncio.run(main())