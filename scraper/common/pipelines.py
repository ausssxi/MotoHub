import os
import requests
import logging
from common.database import SessionLocal, Listing, BikeModel, Shop, Manufacturer

class MotoHubImagePipeline:
    """
    画像をダウンロードし、IDごとのフォルダに連番(0.jpg, 1.jpg...)で保存するパイプライン。
    ダウンロードに成功したファイルのみ、DBの local_image_paths を更新します。
    """
    def open_spider(self, spider):
        self.logger = logging.getLogger(__name__)
        
        # ---------------------------------------------------------
        # 保存先パスの決定ロジック (強化版)
        # ---------------------------------------------------------
        # 1. 環境変数からの指定（Dockerの設定を最優先）
        env_path = os.getenv("IMAGE_STORAGE_PATH")
        
        # 環境変数で listings まで指定されている場合があるので、親の public を基準にする調整
        if env_path and env_path.endswith('/listings'):
            env_path = os.path.dirname(env_path) # .../public になる

        # パス候補リスト
        candidates = []
        if env_path:
            candidates.append(env_path)
        
        # その他の候補（自動検知）
        current_dir = os.path.dirname(os.path.abspath(__file__))
        scraper_root = os.path.dirname(current_dir)
        project_root = os.path.dirname(scraper_root)

        candidates.append("/var/www/storage/app/public") # Docker本番標準
        candidates.append(os.path.join(project_root, "backend", "storage", "app", "public"))
        candidates.append(os.path.join(project_root, "storage", "app", "public"))

        # 有効なパスを探索
        self.storage_base = None
        for path in candidates:
            if os.path.exists(path):
                self.storage_base = path
                break
        
        # 見つからなかった場合、Docker標準パスを強制使用し、作成を試みる
        if not self.storage_base:
            self.storage_base = "/var/www/storage/app/public"
            try:
                os.makedirs(self.storage_base, exist_ok=True)
                self.logger.info(f"Created storage directory: {self.storage_base}")
            except Exception as e:
                self.logger.error(f"Failed to create storage directory: {e}")

        self.logger.info(f"📷 Image storage root: {self.storage_base}")

    def process_item(self, item, spider):
        image_urls = item.get('image_urls')
        if not image_urls:
            return item

        if isinstance(image_urls, str):
            image_urls = [image_urls]

        target_type = item.get('target_type', 'listing') 
        target_id = item.get('id')

        if not target_id:
            return item

        local_paths = []
        
        # 1. 保存先ディレクトリの決定
        shard = str(target_id % 100).zfill(2)
        
        if target_type == 'listing':
            site_name = spider.site_name.lower() if hasattr(spider, 'site_name') else 'other'
            rel_dir = f"{target_type}s/{site_name}/{shard}/{target_id}"
        elif target_type == 'manufacturer':
            rel_dir = f"{target_type}s/{target_id}"
        else:
            rel_dir = f"{target_type}s/{shard}/{target_id}"

        abs_dir = os.path.join(self.storage_base, rel_dir)
        
        # 権限エラーなどで落ちないようにtry-exceptで囲む
        try:
            os.makedirs(abs_dir, exist_ok=True)
        except Exception as e:
            self.logger.error(f"Directory creation failed: {abs_dir} - {e}")
            return item

        # 2. ダウンロード & 保存処理
        for i, url in enumerate(image_urls):
            if not url or not url.startswith('http'):
                continue
                
            try:
                clean_url = url.split('?')[0]
                parts = clean_url.split('.')
                ext = parts[-1].lower() if len(parts) > 1 else 'jpg'
                if len(ext) > 4: ext = 'jpg'
                
                filename = f"{i}.{ext}"
                filepath = os.path.join(abs_dir, filename)
                rel_file_path = f"{rel_dir}/{filename}"

                # ダウンロード成功フラグ
                save_success = False

                if os.path.exists(filepath) and os.path.getsize(filepath) > 0:
                    save_success = True
                else:
                    # User-Agentを設定してブロック回避
                    headers = {
                        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
                    }
                    res = requests.get(url, headers=headers, timeout=10)
                    
                    if res.status_code == 200:
                        with open(filepath, 'wb') as f:
                            f.write(res.content)
                        save_success = True
                    else:
                        self.logger.warning(f"Download failed (Status {res.status_code}): {url}")

                if save_success:
                    local_paths.append(rel_file_path)

            except Exception as e:
                self.logger.warning(f"Download error: {url} - {e}")

        # item情報を更新
        item['local_image_paths'] = local_paths
        
        # 3. DB更新
        self.update_db(item)
        
        return item

    def update_db(self, item):
        db = SessionLocal()
        try:
            target_id = item.get('id')
            target_type = item.get('target_type')
            paths = item.get('local_image_paths')
            
            if not target_id:
                return

            if target_type == 'listing':
                db.query(Listing).filter(Listing.id == target_id).update({"local_image_paths": paths})
            elif target_type == 'model':
                db.query(BikeModel).filter(BikeModel.id == target_id).update({"local_image_path": paths})
            elif target_type == 'shop':
                path = paths[0] if paths else None
                db.query(Shop).filter(Shop.id == target_id).update({"local_image_path": path})
            elif target_type == 'manufacturer':
                path = paths[0] if paths else None
                db.query(Manufacturer).filter(Manufacturer.id == target_id).update({"local_logo_path": path})
            
            db.commit()
        except Exception as e:
            db.rollback()
            self.logger.error(f"DB update error in pipeline: {e}")
        finally:
            db.close()