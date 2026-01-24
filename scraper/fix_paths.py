import sys
import os
import json
import time

# ------------------------------------------------------------------
# 設定: ここで開始位置を指定します
# ------------------------------------------------------------------
START_INDEX = 85100  # ← 85100件目までスキップします

# ------------------------------------------------------------------
# パス設定
# ------------------------------------------------------------------
current_dir = os.path.dirname(os.path.abspath(__file__))
sys.path.append(current_dir)
project_root = os.path.dirname(current_dir)

# 画像ストレージパスの決定
STORAGE_PUBLIC_DIR = os.path.join(project_root, 'backend', 'storage', 'app', 'public')
if not os.path.exists(STORAGE_PUBLIC_DIR):
    STORAGE_PUBLIC_DIR = os.path.join(project_root, 'storage', 'app', 'public')
    if not os.path.exists(STORAGE_PUBLIC_DIR):
        STORAGE_PUBLIC_DIR = '/var/www/storage/app/public'

try:
    from common.database import SessionLocal, Listing
except ImportError:
    print("エラー: common.database が見つかりません。")
    sys.exit(1)

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
            if ext == 'jpeg': return 'jpg'
            return ext
    return "jpg"

def fix_listing_paths():
    db = SessionLocal()
    try:
        print(f"画像ストレージの基準パス: {STORAGE_PUBLIC_DIR}")
        print("DBからデータを取得中...")
        listings = db.query(Listing).filter(Listing.image_urls != None).all()
        
        total = len(listings)
        print(f"対象レコード数: {total} 件")
        print(f"★ {START_INDEX} 件目までスキップし、そこから処理を再開します...")

        count = 0
        updated_count = 0
        missing_files_count = 0

        for item in listings:
            count += 1
            
            # 【再開ロジック】指定件数まではスキップ
            if count < START_INDEX:
                continue

            image_urls = item.image_urls
            
            if isinstance(image_urls, str):
                try:
                    image_urls = json.loads(image_urls)
                except json.JSONDecodeError:
                    continue

            if not image_urls or not isinstance(image_urls, list):
                continue

            # パス生成
            shard = str(item.id % 100).zfill(2)
            site_name = get_site_name(item.site_id)
            new_paths = []
            file_missing = False

            for i, url in enumerate(image_urls):
                ext = get_extension(url)
                rel_path = f"listings/{site_name}/{shard}/{item.id}/{i}.{ext}"
                new_paths.append(rel_path)
                
                # 実在チェック
                full_path = os.path.join(STORAGE_PUBLIC_DIR, rel_path)
                if not os.path.exists(full_path):
                    if not file_missing:
                        file_missing = True
                        missing_files_count += 1

            item.local_image_paths = new_paths
            
            updated_count += 1
            
            # 進捗表示 & コミット (頻度を高めに維持)
            if updated_count % 10 == 0:
                print(f"現在位置: {count}/{total} (今回処理: {updated_count}件) 完了...")
                db.commit()
                # ロック回避のため極小のウェイトを入れる
                time.sleep(0.01)

        db.commit()
        print("-" * 30)
        print(f"完了: 合計 {updated_count} 件のDBパスを修正しました。")
        
        if missing_files_count > 0:
            print(f"\n【重要】 今回の処理範囲で {missing_files_count} 件のレコードで画像ファイルが見つかりませんでした。")
        else:
            print("\nすべての画像ファイルが正しく存在しています。")

    except Exception as e:
        print(f"エラーが発生しました: {e}")
        db.rollback()
    finally:
        db.close()

if __name__ == "__main__":
    val = input(f"{START_INDEX}件目から再開しますか？ (y/n): ")
    if val.lower() == 'y':
        fix_listing_paths()
    else:
        print("キャンセルしました。")