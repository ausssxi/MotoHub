import os
import requests
import hashlib
import logging
from sqlalchemy.orm import Session
from common.database import SessionLocal, Listing, BikeModel, Shop, Manufacturer

class MotoHubImagePipeline:
    """
    あらゆる種類のクローラーから送られてくる画像を、
    適切なディレクトリに保存し、DBの local_image_paths などを更新する共通パイプライン。
    """
    def open_spider(self, spider):
        # スパイダー開始時にDBセッションを開く
        self.db = SessionLocal()
        # Laravel側の public storage パスを基準にする
        self.storage_base = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../backend/storage/app/public"))
        self.logger = logging.getLogger(__name__)

    def process_item(self, item, spider):
        """
        item内の 'image_urls' を見てダウンロードし、
        'target_type' (listing, model, shop) に応じてフォルダを振り分けます。
        """
        image_urls = item.get('image_urls')
        if not image_urls:
            return item

        # リスト形式でなければリスト化
        if isinstance(image_urls, str):
            image_urls = [image_urls]

        target_type = item.get('target_type', 'listing') 
        local_paths = []

        for url in image_urls:
            if not url or not url.startswith('http'):
                continue
                
            try:
                # 1. 保存先ディレクトリの決定 (IDの下2桁でシャッフルして1フォルダのファイル数を抑える)
                shard = str(item.get('id', 0) % 100).zfill(2) if item.get('id') else "00"
                sub_dir = f"{target_type}s"
                
                if target_type == 'listing':
                    site_name = spider.site_name.lower() if hasattr(spider, 'site_name') else 'other'
                    save_dir = os.path.join(self.storage_base, sub_dir, site_name, shard)
                else:
                    save_dir = os.path.join(self.storage_base, sub_dir, shard)

                os.makedirs(save_dir, exist_ok=True)

                # 2. ファイル名の生成 (URLのMD5ハッシュ値を使用して重複を防ぐ)
                ext = url.split('.')[-1].split('?')[0] or 'jpg'
                if len(ext) > 4: ext = 'jpg'
                filename = f"{hashlib.md5(url.encode()).hexdigest()}.{ext}"
                filepath = os.path.join(save_dir, filename)

                # 3. ダウンロード (未保存の場合のみ)
                if not os.path.exists(filepath):
                    res = requests.get(url, timeout=15)
                    if res.status_code == 200:
                        with open(filepath, 'wb') as f:
                            f.write(res.content)
                
                # Web（Laravel）からアクセス可能な相対パスを保持
                rel_path = os.path.relpath(filepath, self.storage_base)
                local_paths.append(rel_path)

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
        """
        try:
            target_id = item.get('id')
            target_type = item.get('target_type')
            paths = item.get('local_image_paths')
            
            if not target_id or not paths:
                return

            if target_type == 'listing':
                self.db.query(Listing).filter(Listing.id == target_id).update({"local_image_paths": paths})
            elif target_type == 'model':
                self.db.query(BikeModel).filter(BikeModel.id == target_id).update({"local_image_path": paths})
            elif target_type == 'shop':
                # shopは通常代表画像1枚
                self.db.query(Shop).filter(Shop.id == target_id).update({"local_image_path": paths[0]})
            
            self.db.commit()
        except Exception as e:
            self.db.rollback()
            self.logger.error(f"DB update error in pipeline: {e}")

    def close_spider(self, spider):
        # スパイダー終了時にセッションを閉じる
        self.db.close()