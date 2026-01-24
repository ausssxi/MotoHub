import os
import requests
import logging
from common.database import SessionLocal, Listing, BikeModel, Shop, Manufacturer

class MotoHubImagePipeline:
    """
    画像をダウンロードし、IDごとのフォルダに連番(0.jpg, 1.jpg...)で保存するパイプライン。
    DBの local_image_paths も更新します。
    """
    def open_spider(self, spider):
        # Laravel側の public storage パスを基準にする
        # Docker環境 (/var/www) またはローカル開発環境のパスを考慮
        self.storage_base = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../backend/storage/app/public"))
        if not os.path.exists(self.storage_base):
            # 万が一パスが解決できない場合の予備（Docker環境想定）
            self.storage_base = "/var/www/storage/app/public"
            
        self.logger = logging.getLogger(__name__)

    def process_item(self, item, spider):
        """
        保存ロジックを変更: 
        ハッシュ名ではなく、IDフォルダを作成し、その中に連番(0.jpg, 1.jpg)で保存します。
        """
        image_urls = item.get('image_urls')
        if not image_urls:
            return item

        # リスト形式でなければリスト化
        if isinstance(image_urls, str):
            image_urls = [image_urls]

        target_type = item.get('target_type', 'listing') 
        target_id = item.get('id')

        # IDがない場合は保存場所が決まらないためスキップ
        if not target_id:
            return item

        local_paths = []
        
        # 1. 保存先ディレクトリの決定
        shard = str(target_id % 100).zfill(2)
        
        # サイト名取得 (listingsの場合)
        if target_type == 'listing':
            site_name = spider.site_name.lower() if hasattr(spider, 'site_name') else 'other'
            # 構成: listings/site_name/shard/id
            rel_dir = f"{target_type}s/{site_name}/{shard}/{target_id}"
        elif target_type == 'manufacturer':
            # 構成: manufacturers/id
            rel_dir = f"{target_type}s/{target_id}"
        else:
            # shops/shard/id, bike_models/shard/id
            rel_dir = f"{target_type}s/{shard}/{target_id}"

        # 絶対パスの生成とフォルダ作成
        abs_dir = os.path.join(self.storage_base, rel_dir)
        os.makedirs(abs_dir, exist_ok=True)

        # 2. 連番で保存処理
        for i, url in enumerate(image_urls):
            if not url or not url.startswith('http'):
                continue
                
            try:
                # 拡張子判定
                clean_url = url.split('?')[0]
                parts = clean_url.split('.')
                ext = parts[-1].lower() if len(parts) > 1 else 'jpg'
                if len(ext) > 4: ext = 'jpg'
                
                # ファイル名: 0.jpg, 1.jpg ...
                filename = f"{i}.{ext}"
                filepath = os.path.join(abs_dir, filename)

                # ダウンロード (上書きモード、または存在チェック)
                # 画像が更新されている可能性もあるため、基本的には上書き推奨ですが
                # 負荷軽減のため存在チェックを入れる場合は以下のようにします
                if not os.path.exists(filepath):
                    res = requests.get(url, timeout=15)
                    if res.status_code == 200:
                        with open(filepath, 'wb') as f:
                            f.write(res.content)
                    else:
                        self.logger.warning(f"Image download failed status {res.status_code}: {url}")
                
                # DB保存用の相対パス
                # listings/goobike/01/12345/0.jpg
                rel_file_path = f"{rel_dir}/{filename}"
                local_paths.append(rel_file_path)

            except Exception as e:
                self.logger.warning(f"Failed to download image: {url} - {e}")

        # 加工したパスをitemに戻す
        item['local_image_paths'] = local_paths
        
        # 4. DBへの即時反映
        self.update_db(item)
        
        return item

    def update_db(self, item):
        """
        取得したローカルパスをDBの各テーブルに書き込みます。
        トランザクション分離問題を避けるため、都度セッションを作成します。
        """
        db = SessionLocal()
        try:
            target_id = item.get('id')
            target_type = item.get('target_type')
            paths = item.get('local_image_paths')
            
            if not target_id or not paths:
                return

            if target_type == 'listing':
                db.query(Listing).filter(Listing.id == target_id).update({"local_image_paths": paths})
            elif target_type == 'model':
                db.query(BikeModel).filter(BikeModel.id == target_id).update({"local_image_path": paths})
            elif target_type == 'shop':
                # shopは通常代表画像1枚
                db.query(Shop).filter(Shop.id == target_id).update({"local_image_path": paths[0]})
            elif target_type == 'manufacturer':
                db.query(Manufacturer).filter(Manufacturer.id == target_id).update({"local_logo_path": paths[0]})
            
            db.commit()
        except Exception as e:
            db.rollback()
            self.logger.error(f"DB update error in pipeline: {e}")
        finally:
            db.close()