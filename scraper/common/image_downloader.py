import os
import asyncio
import httpx
import json
import mimetypes
import random
import sys
import time
from sqlalchemy import create_engine, Column, BigInteger, JSON, Text
from sqlalchemy.orm import DeclarativeBase, sessionmaker
from dotenv import load_dotenv

# 1. 環境変数の読み込み
# 実行ファイルからの相対パスで .env を探す
current_dir = os.path.dirname(os.path.abspath(__file__))
env_path = os.path.join(current_dir, '..', '.env')
load_dotenv(dotenv_path=env_path)

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

# DB設定: セキュリティのため機密情報はデフォルト値を設定せず必須（required=True）とする
DB_USER = get_env_or_exit("DB_USERNAME")
DB_PASS = get_env_or_exit("DB_PASSWORD")
DB_NAME = get_env_or_exit("DB_DATABASE")

# 接続先やポートは、機密情報ではないため利便性のためにデフォルト値を残しても許容される
DB_HOST = get_env_or_exit("DB_HOST", default="db")
DB_PORT = get_env_or_exit("DB_PORT", default="3306")

DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}"
engine = create_engine(DATABASE_URL)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

# 2. 保存先パスの設定
DEFAULT_STORAGE_PATH = os.path.abspath(os.path.join(current_dir, "../../backend/storage/app/public/listings"))
STORAGE_BASE_PATH = os.getenv("IMAGE_STORAGE_PATH", DEFAULT_STORAGE_PATH)

class Base(DeclarativeBase): pass

class Listing(Base):
    __tablename__ = "listings"
    id = Column(BigInteger, primary_key=True)
    site_id = Column(BigInteger)
    image_urls = Column(JSON)
    local_image_paths = Column(JSON, nullable=True)

async def download_image(client, url, site_name, shard, listing_id, index):
    """1枚の画像をダウンロードして適切な拡張子で保存"""
    try:
        headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        }
        
        # サーバー負荷軽減のためランダム待機
        await asyncio.sleep(random.uniform(0.1, 0.3))
        
        resp = await client.get(url, headers=headers, timeout=15.0)
        if resp.status_code != 200:
            return None

        content_type = resp.headers.get("Content-Type", "")
        ext = mimetypes.guess_extension(content_type.split(';')[0])
        if not ext:
            ext = ".jpg"
        
        filename = f"{index}{ext}"
        rel_path = f"{site_name}/{shard}/{listing_id}/{filename}"
        abs_path = os.path.join(STORAGE_BASE_PATH, rel_path)

        # フォルダ作成
        os.makedirs(os.path.dirname(abs_path), exist_ok=True)
        
        with open(abs_path, "wb") as f:
            f.write(resp.content)
            
        return rel_path

    except Exception as e:
        print(f"      Download Error ({url}): {e}")
    return None

async def process_listing(client, listing):
    """1つの出品情報の画像を全て処理"""
    if not listing.image_urls or not isinstance(listing.image_urls, list):
        return None

    site_name = "goobike" if listing.site_id == 1 else "bds"
    shard = str(listing.id % 100).zfill(2)
    
    downloaded_paths = []
    for i, url in enumerate(listing.image_urls):
        saved_rel_path = await download_image(client, url, site_name, shard, listing.id, i)
        if saved_rel_path:
            downloaded_paths.append(saved_rel_path)
    
    return downloaded_paths if downloaded_paths else None

async def run():
    print(f"DEBUG: 画像保存ベースパス -> {STORAGE_BASE_PATH}")
    
    # 書き込み権限のチェック
    if not os.path.exists(STORAGE_BASE_PATH):
        os.makedirs(STORAGE_BASE_PATH, exist_ok=True)
        
    if not os.access(STORAGE_BASE_PATH, os.W_OK):
        print(f"致命的エラー: {STORAGE_BASE_PATH} への書き込み権限がありません。")
        return

    batch_count = 1
    total_downloaded = 0

    while True:
        db = SessionLocal()
        try:
            # 未処理のレコードを取得 (NULLのもの)
            query = db.query(Listing).filter(
                Listing.image_urls != None,
                Listing.local_image_paths == None
            )
            
            # バッチサイズを設定 (100件ずつ処理)
            batch_size = 100
            target_listings = query.limit(batch_size).all() 

            if not target_listings:
                print("\nすべての未処理画像のダウンロードが完了しました。")
                break

            print(f"\n--- バッチ {batch_count}: {len(target_listings)} 件の処理を開始 ---")

            async with httpx.AsyncClient(follow_redirects=True) as client:
                for listing in target_listings:
                    print(f"  車両ID:{listing.id} の画像を処理中...")
                    paths = await process_listing(client, listing)
                    
                    if paths:
                        listing.local_image_paths = paths
                        db.commit() # 1件ごとにコミットして進捗を確実に保存
                        total_downloaded += 1
                        print(f"    -> {len(paths)} 枚保存完了 (累計: {total_downloaded}車両)")
                    else:
                        # 取得できなかった場合は空配列をいれてスキップ（リトライループ防止）
                        listing.local_image_paths = []
                        db.commit()
                        print(f"    -> 画像なしまたは取得失敗につきスキップ")

            batch_count += 1
            # 次のバッチの前に少し休止
            await asyncio.sleep(1)

        except Exception as e:
            print(f"バッチ処理中にエラーが発生しました: {e}")
            await asyncio.sleep(5) # エラー時は少し長めに待機
        finally:
            db.close()

if __name__ == "__main__":
    asyncio.run(run())