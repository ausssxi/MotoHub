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
        # 保存先パスの決定 (環境によるパスずれを防止)
        # ---------------------------------------------------------
        # このファイル(pipelines.py)から見たプロジェクトルートを特定
        # pipelines.py -> common -> scraper -> (root)
        current_dir = os.path.dirname(os.path.abspath(__file__))
        scraper_root = os.path.dirname(current_dir)
        project_root = os.path.dirname(scraper_root)

        # 候補1: ローカル開発/標準構成 (backend/storage/app/public)
        path_candidate_1 = os.path.join(project_root, "backend", "storage", "app", "public")
        # 候補2: Docker本番環境 (/var/www/storage/app/public)
        path_candidate_2 = "/var/www/storage/app/public"
        # 候補3: 簡易構成 (storage/app/public)
        path_candidate_3 = os.path.join(project_root, "storage", "app", "public")

        if os.path.exists(path_candidate_1):
            self.storage_base = path_candidate_1
        elif os.path.exists(path_candidate_2):
            self.storage_base = path_candidate_2
        elif os.path.exists(path_candidate_3):
            self.storage_base = path_candidate_3
        else:
            # 最終手段としてDockerパスを強制設定（ディレクトリ作成を試みるため）
            self.storage_base = path_candidate_2
            
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
        os.makedirs(abs_dir, exist_ok=True)

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

                # ★修正ポイント: ダウンロード成功フラグ
                save_success = False

                if os.path.exists(filepath) and os.path.getsize(filepath) > 0:
                    # 既にファイルがあり、サイズが0でなければ成功とみなす
                    save_success = True
                else:
                    # ダウンロード実行
                    # ★修正: User-Agentを設定してブロックを回避 (403エラー対策)
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

                # ★成功したときだけパスリストに追加
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

            # 画像が1枚も取れなかった場合は、空配列（またはNULL）で更新するか、更新しないか
            # ここでは「現状を反映する」ため、空でも更新します
            
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