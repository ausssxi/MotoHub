import sys
import os
import json

# ------------------------------------------------------------------
# パス設定: commonモジュールをインポートできるようにする
# ------------------------------------------------------------------
current_dir = os.path.dirname(os.path.abspath(__file__))
# 1つ上の階層（プロジェクトルート）をパスに追加
sys.path.append(os.path.dirname(current_dir))

try:
    from common.database import SessionLocal, Listing
except ImportError:
    print("エラー: common.database が見つかりません。")
    print("scripts ディレクトリの中で実行しているか確認してください。")
    sys.exit(1)

def get_site_name(site_id):
    """site_id からディレクトリ名を決定"""
    site_map = {
        1: "goobike",
        2: "bds",
        3: "webike"
    }
    return site_map.get(site_id, "other")

def get_extension(url):
    """URLから拡張子を簡易推定"""
    if not url:
        return "jpg"
    
    # クエリパラメータ削除 (?v=123 等)
    clean_url = url.split('?')[0]
    # 最後のドット以降を取得
    parts = clean_url.split('.')
    if len(parts) > 1:
        ext = parts[-1].lower()
        if ext in ['png', 'gif', 'webp', 'jpeg', 'bmp']:
            if ext == 'jpeg': return 'jpg'
            return ext
    
    # デフォルトはjpg
    return "jpg"

def fix_listing_paths():
    db = SessionLocal()
    try:
        # local_image_paths が NULL でない（画像を持っている）レコードを取得
        # ※全件処理したい場合はフィルタを外してください
        print("DBからデータを取得中...")
        listings = db.query(Listing).filter(Listing.image_urls != None).all()
        
        total = len(listings)
        print(f"対象レコード数: {total} 件")
        print("パスの修復を開始します...")

        count = 0
        updated_count = 0

        for item in listings:
            # 画像URLリストの取得
            image_urls = item.image_urls
            
            # DBドライバによってはJSONが文字列で返ってくる場合の対応
            if isinstance(image_urls, str):
                try:
                    image_urls = json.loads(image_urls)
                except json.JSONDecodeError:
                    continue

            if not image_urls or not isinstance(image_urls, list):
                continue

            # -------------------------------------------------
            # 新しいパスの生成 
            # 形式: listings/{site_name}/{shard}/{id}/{index}.{ext}
            # -------------------------------------------------
            
            # シャード (IDの下2桁 00-99)
            shard = str(item.id % 100).zfill(2)
            
            # サイト名
            site_name = get_site_name(item.site_id)
            
            new_paths = []
            for i, url in enumerate(image_urls):
                ext = get_extension(url)
                
                # パス生成
                path = f"listings/{site_name}/{shard}/{item.id}/{i}.{ext}"
                new_paths.append(path)

            # DB更新
            # 配列をそのまま入れる（SQLAlchemyがJSON型として処理）
            item.local_image_paths = new_paths
            
            count += 1
            updated_count += 1
            
            # 進捗表示 & 定期コミット
            if count % 100 == 0:
                print(f"{count}/{total} 件処理完了...")
                db.commit()

        # 最終コミット
        db.commit()
        print("-" * 30)
        print(f"完了: 合計 {updated_count} 件のパスを以前の形式（IDフォルダ型）に修正しました。")

    except Exception as e:
        print(f"エラーが発生しました: {e}")
        db.rollback()
    finally:
        db.close()

if __name__ == "__main__":
    print("=" * 50)
    print("【 Listingパス修復スクリプト 】")
    print("DBの 'local_image_paths' を強制的に 'listings/site/shard/id/番号.jpg' 形式に書き換えます。")
    print("※注意: 実際のファイル移動は行いません。整合性を取るために実行してください。")
    print("=" * 50)
    
    val = input("実行しますか？ (y/n): ")
    if val.lower() == 'y':
        fix_listing_paths()
    else:
        print("キャンセルしました。")