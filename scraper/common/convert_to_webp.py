import os
import sys
import json
import logging
from PIL import Image
from sqlalchemy import create_engine, Column, BigInteger, JSON, Text, String
from sqlalchemy.orm import DeclarativeBase, sessionmaker
from dotenv import load_dotenv

# ==========================================
# 1. 環境設定 & データベース定義
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
env_path = os.path.join(current_dir, '..', '../.env')
load_dotenv(dotenv_path=env_path)

def get_env_or_exit(key, default=None):
    val = os.getenv(key, default)
    if val is None:
        print(f"致命的エラー: 必須の環境変数 '{key}' が設定されていません。")
        sys.exit(1)
    return val

DATABASE_URL = f"mysql+pymysql://{get_env_or_exit('DB_USERNAME')}:{get_env_or_exit('DB_PASSWORD')}@{get_env_or_exit('DB_HOST', 'db')}:{get_env_or_exit('DB_PORT', '3306')}/{get_env_or_exit('DB_DATABASE')}"

engine = create_engine(DATABASE_URL, pool_pre_ping=True)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

DEFAULT_STORAGE_PATH = os.path.abspath(os.path.join(current_dir, "../../backend/storage/app/public"))
STORAGE_BASE_PATH = os.getenv("IMAGE_STORAGE_PATH", DEFAULT_STORAGE_PATH)
if STORAGE_BASE_PATH.endswith(("/listings", "/listings/")):
    STORAGE_BASE_PATH = os.path.dirname(STORAGE_BASE_PATH.rstrip('/'))

class Base(DeclarativeBase): pass

class Listing(Base):
    __tablename__ = "listings"
    id = Column(BigInteger, primary_key=True)
    local_image_paths = Column(JSON, nullable=True)

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True)
    local_image_path = Column(JSON, nullable=True)

class Manufacturer(Base):
    __tablename__ = "manufacturers"
    id = Column(BigInteger, primary_key=True)
    local_logo_path = Column(String(255), nullable=True)

class Shop(Base):
    __tablename__ = "shops"
    id = Column(BigInteger, primary_key=True)
    local_image_path = Column(String(255), nullable=True)

# ==========================================
# 2. 画像変換ロジック
# ==========================================
def convert_file_to_webp(old_rel_path):
    """ローカルのJPEG/PNG等をWebPに変換し、新しい相対パスを返す"""
    if not old_rel_path or str(old_rel_path).lower().endswith('.webp'):
        return old_rel_path # すでにWebPならそのまま返す

    old_abs_path = os.path.join(STORAGE_BASE_PATH, old_rel_path.lstrip('/'))
    
    if not os.path.exists(old_abs_path):
        return old_rel_path # ファイルが存在しない場合はDBのパスをそのまま維持

    try:
        with Image.open(old_abs_path) as img:
            # 透過対策とRGB変換
            if img.mode in ('RGBA', 'LA') or (img.mode == 'P' and 'transparency' in img.info):
                background = Image.new('RGB', img.size, (255, 255, 255))
                mask = img.split()[3] if img.mode == 'RGBA' else img.convert('RGBA').split()[3]
                background.paste(img, mask=mask)
                img = background
            elif img.mode != 'RGB':
                img = img.convert('RGB')
            
            # リサイズ
            MAX_WIDTH = 800
            if img.width > MAX_WIDTH:
                ratio = MAX_WIDTH / float(img.width)
                new_height = int(img.height * ratio)
                img = img.resize((MAX_WIDTH, new_height), Image.Resampling.LANCZOS)
            
            # 新しいパスを生成 (拡張子を .webp に変更)
            # 例: listings/goobike/01/1/0.jpg -> listings/goobike/01/1/0.webp
            base_name, _ = os.path.splitext(old_rel_path)
            new_rel_path = f"{base_name}.webp"
            new_abs_path = os.path.join(STORAGE_BASE_PATH, new_rel_path.lstrip('/'))
            
            # WebPで保存
            img.save(new_abs_path, format="WEBP", quality=80, method=4)
            
        # 成功したら古いファイル(JPEGなど)を削除して容量を空ける
        if os.path.exists(old_abs_path) and old_abs_path != new_abs_path:
            os.remove(old_abs_path)
            
        return new_rel_path
        
    except Exception as e:
        logging.error(f"変換エラー ({old_rel_path}): {e}")
        return old_rel_path

# ==========================================
# 3. DB更新ロジック
# ==========================================
def process_table(db, target_class, table_label, path_field):
    print(f"\n--- {table_label} の一括変換を開始します ---")
    batch_size = 500
    offset = 0
    total_converted = 0
    
    while True:
        # パスが入っているレコードを取得
        query = db.query(target_class).filter(getattr(target_class, path_field) != None).offset(offset).limit(batch_size)
        items = query.all()
        
        if not items:
            break
            
        for item in items:
            raw_paths = getattr(item, path_field)
            if not raw_paths:
                continue

            column_type = getattr(target_class, path_field).type
            is_json = isinstance(column_type, JSON) or isinstance(raw_paths, list)
            
            needs_update = False
            
            if is_json:
                path_list = raw_paths if isinstance(raw_paths, list) else json.loads(raw_paths)
                new_path_list = []
                for p in path_list:
                    new_p = convert_file_to_webp(p)
                    new_path_list.append(new_p)
                    if p != new_p:
                        needs_update = True
                        
                if needs_update:
                    setattr(item, path_field, new_path_list)
            else:
                # 単一の文字列(String)の場合
                p = raw_paths
                new_p = convert_file_to_webp(p)
                if p != new_p:
                    needs_update = True
                    setattr(item, path_field, new_p)
            
            if needs_update:
                total_converted += 1
                
        db.commit()
        offset += batch_size
        print(f"  {offset}件目まで処理完了... (変換数: {total_converted})")

    print(f"✅ {table_label} の変換完了！ 合計 {total_converted} レコードを更新しました。")

def main():
    logging.basicConfig(level=logging.ERROR)
    print("🚀 既存画像のWebP一括変換スクリプトを起動します")
    
    db = SessionLocal()
    try:
        process_table(db, Manufacturer, "manufacturers", "local_logo_path")
        process_table(db, Shop, "shops", "local_image_path")
        process_table(db, BikeModel, "bike_models", "local_image_path")
        process_table(db, Listing, "listings", "local_image_paths")
    finally:
        db.close()
        
    print("\n🎉 全ての変換作業が完了しました！")

if __name__ == "__main__":
    main()