import sys
import os
import json
import time
import requests
import logging

# ==========================================
# 0. パス設定 (toolsフォルダ対応版)
# ==========================================
# このファイル: .../scraper/tools/recover_images.py
current_dir = os.path.dirname(os.path.abspath(__file__)) 

# 親ディレクトリ (scraperフォルダ)
scraper_root = os.path.dirname(current_dir)

# さらに親 (プロジェクトルート: motohub)
project_root = os.path.dirname(scraper_root)

# scraperフォルダを検索パスに追加 (これで from common... が通る)
if scraper_root not in sys.path:
    sys.path.append(scraper_root)

# commonモジュールのインポート
try:
    from common.database import SessionLocal, Listing
except ImportError:
    print("エラー: commonモジュールが見つかりません。")
    print(f"検索パス: {sys.path}")
    sys.exit(1)

# ログ設定
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - [%(levelname)s] - %(message)s',
    handlers=[logging.StreamHandler()]
)
logger = logging.getLogger(__name__)

# ==========================================
# 設定: 開始位置
# ==========================================
START_INDEX = 100000  # ← 100000件目までスキップして開始

# ==========================================
# 1. 保存先ディレクトリの特定
# ==========================================
CANDIDATE_PATHS = [
    # 本番Docker環境の絶対パス（最優先）
    '/var/www/storage/app/public',
    # ローカル開発環境 (backendがある場合)
    os.path.join(project_root, 'backend', 'storage', 'app', 'public'),
    # 簡易構成
    os.path.join(project_root, 'storage', 'app', 'public'),
]

STORAGE_PUBLIC_DIR = None
for p in CANDIDATE_PATHS:
    if os.path.exists(p):
        STORAGE_PUBLIC_DIR = p
        break

if not STORAGE_PUBLIC_DIR:
    # 見つからない場合はDockerパスをデフォルトに設定
    STORAGE_PUBLIC_DIR = '/var/www/storage/app/public'

logger.info(f"Target Storage Path: {STORAGE_PUBLIC_DIR}")

# ==========================================
# 2. ユーティリティ関数
# ==========================================
def get_site_name(site_id):
    site_map = {1: "goobike", 2: "bds", 3: "webike"}
    return site_map.get(site_id, "other")

def get_extension(url):
    if not url: return "jpg"
    clean_url = url.split('?')[0]
    parts = clean_url.split('.')
    if len(parts) > 1:
        ext = parts[-1].lower()
        if ext in ['png', 'gif', 'webp', 'jpeg', 'bmp']:
            return 'jpg' if ext == 'jpeg' else ext
    return "jpg"

def download_file(url, filepath):
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        }
        response = requests.get(url, headers=headers, timeout=10)
        
        if response.status_code == 200:
            with open(filepath, 'wb') as f:
                f.write(response.content)
            return True
        else:
            return False
    except Exception as e:
        logger.warning(f"Download error: {e} URL: {url}")
        return False

# ==========================================
# 3. メイン処理
# ==========================================
def main():
    db = SessionLocal()
    try:
        logger.info("Fetching listings from database...")
        listings = db.query(Listing).filter(Listing.image_urls != None).all()
        
        total = len(listings)
        logger.info(f"Found {total} listings. Skipping first {START_INDEX} items...")

        processed_count = 0
        download_count = 0
        updated_count = 0

        for listing in listings:
            processed_count += 1
            
            # 【再開ロジック】指定件数まではスキップ
            if processed_count < START_INDEX:
                continue
            
            image_urls = listing.image_urls
            if isinstance(image_urls, str):
                try: image_urls = json.loads(image_urls)
                except: continue
            
            if not image_urls or not isinstance(image_urls, list):
                continue

            # パス計算
            shard = str(listing.id % 100).zfill(2)
            site_name = get_site_name(listing.site_id)
            
            rel_dir = f"listings/{site_name}/{shard}/{listing.id}"
            abs_dir = os.path.join(STORAGE_PUBLIC_DIR, rel_dir)
            
            if not os.path.exists(abs_dir):
                os.makedirs(abs_dir, exist_ok=True)

            local_paths = []
            
            for i, url in enumerate(image_urls):
                ext = get_extension(url)
                filename = f"{i}.{ext}"
                filepath = os.path.join(abs_dir, filename)
                rel_path = f"{rel_dir}/{filename}"

                # ファイルがない、またはサイズ0ならダウンロード
                if not os.path.exists(filepath) or os.path.getsize(filepath) == 0:
                    if download_file(url, filepath):
                        download_count += 1
                        time.sleep(0.2) # 負荷軽減
                
                if os.path.exists(filepath) and os.path.getsize(filepath) > 0:
                    local_paths.append(rel_path)

            # DB更新チェック
            if local_paths:
                current_paths = listing.local_image_paths
                if isinstance(current_paths, str):
                    try: current_paths = json.loads(current_paths)
                    except: current_paths = []
                
                # 差分がある場合のみ更新
                if json.dumps(current_paths) != json.dumps(local_paths):
                    listing.local_image_paths = local_paths
                    updated_count += 1
            
            if processed_count % 50 == 0:
                db.commit()
                logger.info(f"Progress: {processed_count}/{total} | Downloaded: {download_count} | Updated DB: {updated_count}")

        db.commit()
        logger.info("==========================================")
        logger.info(f"Recovery Completed!")
        logger.info(f"Total Downloaded: {download_count} images")
        logger.info(f"Total DB Updated: {updated_count} records")
        logger.info("==========================================")

    except Exception as e:
        logger.error(f"Critical Error: {e}")
        db.rollback()
    finally:
        db.close()

if __name__ == "__main__":
    print(f"--- 画像強制ダウンロード＆修復ツール (Start Index: {START_INDEX}) ---")
    main()