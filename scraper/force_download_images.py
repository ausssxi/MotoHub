import sys
import os
import json
import time
import requests
import logging
from sqlalchemy.orm import Session

# ==========================================
# 0. パス設定
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir) # プロジェクトルート
if current_dir not in sys.path:
    sys.path.append(current_dir)

# commonモジュールのインポート用
from common.database import SessionLocal, Listing

# ログ設定
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - [%(levelname)s] - %(message)s',
    handlers=[logging.StreamHandler()]
)
logger = logging.getLogger(__name__)

# ==========================================
# 1. 保存先ディレクトリの特定
# ==========================================
# 候補のパスを順にチェック
CANDIDATE_PATHS = [
    os.path.join(parent_dir, 'backend', 'storage', 'app', 'public'), # ローカル開発標準
    '/var/www/storage/app/public',                                   # Docker本番
    os.path.join(parent_dir, 'storage', 'app', 'public'),            # 簡易構成
]

STORAGE_PUBLIC_DIR = None
for p in CANDIDATE_PATHS:
    if os.path.exists(p):
        STORAGE_PUBLIC_DIR = p
        break

# 見つからない場合は強制的にDockerパス（ディレクトリ作成試行用）
if not STORAGE_PUBLIC_DIR:
    STORAGE_PUBLIC_DIR = '/var/www/storage/app/public'

logger.info(f"Target Storage Path: {STORAGE_PUBLIC_DIR}")

# ==========================================
# 2. ユーティリティ関数
# ==========================================
def get_site_name(site_id):
    site_map = {1: "goobike", 2: "bds", 3: "webike"}
    return site_map.get(site_id, "other")

def get_extension(url):
    """URLから拡張子を簡易推定"""
    if not url: return "jpg"
    clean_url = url.split('?')[0]
    parts = clean_url.split('.')
    if len(parts) > 1:
        ext = parts[-1].lower()
        if ext in ['png', 'gif', 'webp', 'jpeg', 'bmp']:
            return 'jpg' if ext == 'jpeg' else ext
    return "jpg"

def download_file(url, filepath):
    """画像をダウンロードして保存"""
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        }
        # タイムアウト短めでサクサク進める
        response = requests.get(url, headers=headers, timeout=10)
        
        if response.status_code == 200:
            with open(filepath, 'wb') as f:
                f.write(response.content)
            return True
        else:
            # 404などはログに残すが処理は止めない
            # logger.warning(f"Download failed (Status {response.status_code}): {url}")
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
        # 画像URLがある全レコードを取得
        # ※件数が多い場合は start/end で区切っても良いですが、今回は全件回します
        logger.info("Fetching listings from database...")
        listings = db.query(Listing).filter(Listing.image_urls != None).all()
        
        total = len(listings)
        logger.info(f"Found {total} listings. Starting recovery process...")

        processed_count = 0
        download_count = 0
        updated_count = 0

        for listing in listings:
            processed_count += 1
            
            # --- 画像URLリストの解析 ---
            image_urls = listing.image_urls
            if isinstance(image_urls, str):
                try:
                    image_urls = json.loads(image_urls)
                except:
                    continue
            
            if not image_urls or not isinstance(image_urls, list):
                continue

            # --- 保存パスの計算 ---
            shard = str(listing.id % 100).zfill(2)
            site_name = get_site_name(listing.site_id)
            
            # 相対ディレクトリ: listings/goobike/01/12345
            rel_dir = f"listings/{site_name}/{shard}/{listing.id}"
            abs_dir = os.path.join(STORAGE_PUBLIC_DIR, rel_dir)
            
            # ディレクトリ作成
            if not os.path.exists(abs_dir):
                os.makedirs(abs_dir, exist_ok=True)

            local_paths = []
            
            # --- 各画像のダウンロード処理 ---
            for i, url in enumerate(image_urls):
                ext = get_extension(url)
                filename = f"{i}.{ext}"
                filepath = os.path.join(abs_dir, filename)
                rel_path = f"{rel_dir}/{filename}"

                # ★ここが重要: ファイルがない場合のみダウンロード
                if not os.path.exists(filepath) or os.path.getsize(filepath) == 0:
                    # ダウンロード実行
                    if download_file(url, filepath):
                        download_count += 1
                        # サーバー負荷軽減のため少し待つ (0.2秒)
                        time.sleep(0.2)
                
                # ファイルが存在することを確認してからリストに追加
                if os.path.exists(filepath) and os.path.getsize(filepath) > 0:
                    local_paths.append(rel_path)

            # --- DB情報の更新 ---
            # パス情報の不整合を直すため、再生成したパスリストで上書き
            if local_paths:
                # 配列が現在のDB値と違う場合のみ更新
                current_paths = listing.local_image_paths
                if isinstance(current_paths, str):
                    try: current_paths = json.loads(current_paths)
                    except: current_paths = []
                
                if current_paths != local_paths:
                    listing.local_image_paths = local_paths
                    updated_count += 1
            
            # --- 進捗表示 & コミット ---
            if processed_count % 50 == 0:
                db.commit()
                logger.info(f"Progress: {processed_count}/{total} | Downloaded: {download_count} files | Updated DB: {updated_count} rows")

        # 最終コミット
        db.commit()
        logger.info("==========================================")
        logger.info(f"Recovery Completed!")
        logger.info(f"Total Processed: {processed_count}")
        logger.info(f"Total Downloaded: {download_count} images")
        logger.info(f"Total DB Updated: {updated_count} records")
        logger.info("==========================================")

    except Exception as e:
        logger.error(f"Critical Error: {e}")
        db.rollback()
    finally:
        db.close()

if __name__ == "__main__":
    print("----------------------------------------------------------------")
    print("【画像強制ダウンロード＆修復ツール】")
    print("DBのURLリストを使って画像を再ダウンロードし、listings/site/shard/id/0.jpg 形式で保存します。")
    print("※ 既にファイルがある場合はスキップします。")
    print("----------------------------------------------------------------")
    
    val = input("実行しますか？ (y/n): ")
    if val.lower() == 'y':
        main()
    else:
        print("キャンセルしました。")