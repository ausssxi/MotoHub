import os
import sys
import json
import logging
from PIL import Image
from sqlalchemy import create_engine, Column, BigInteger, JSON, String
from sqlalchemy.orm import DeclarativeBase, sessionmaker
from dotenv import load_dotenv

# ==========================================
# 1. 環境設定
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
env_path = os.path.join(current_dir, '..', '../.env')
load_dotenv(dotenv_path=env_path)

def get_env_or_exit(key, default=None):
    val = os.getenv(key, default)
    if val is None:
        print(f"致命的エラー: '{key}' が設定されていません。")
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
# 2. 強制変換＆修復ロジック
# ==========================================
def force_convert_and_rescue(db_path):
    if not db_path:
        return db_path

    # 万が一、DBにJSON文字列のまま保存されていた場合のクリーニング
    if isinstance(db_path, str) and db_path.startswith('['):
        try:
            parsed = json.loads(db_path)
            if parsed:
                db_path = parsed[0]
        except:
            pass

    # 拡張子を除いたベース名を取得
    base_name = os.path.splitext(db_path)[0]
    expected_webp_rel = f"{base_name}.webp"
    expected_webp_abs = os.path.join(STORAGE_BASE_PATH, expected_webp_rel.lstrip('/'))

    converted = False
    searched_paths = []

    # 大文字・小文字、JPEGなどあらゆる可能性を探る
    for ext in ['.jpg', '.jpeg', '.png', '.JPG', '.JPEG', '.PNG']:
        old_abs = os.path.join(STORAGE_BASE_PATH, base_name.lstrip('/') + ext)
        searched_paths.append(old_abs)
        
        if os.path.exists(old_abs):
            try:
                with Image.open(old_abs) as img:
                    if img.mode in ('RGBA', 'LA') or (img.mode == 'P' and 'transparency' in img.info):
                        bg = Image.new('RGB', img.size, (255, 255, 255))
                        mask = img.split()[3] if img.mode == 'RGBA' else img.convert('RGBA').split()[3]
                        bg.paste(img, mask=mask)
                        img = bg
                    elif img.mode != 'RGB':
                        img = img.convert('RGB')
                    
                    if img.width > 800:
                        ratio = 800 / float(img.width)
                        img = img.resize((800, int(img.height * ratio)), Image.Resampling.LANCZOS)
                    
                    img.save(expected_webp_abs, format="WEBP", quality=80, method=4)
                    os.chmod(expected_webp_abs, 0o644)
                
                if old_abs != expected_webp_abs:
                    try:
                        os.remove(old_abs)
                    except Exception:
                        pass
                
                print(f"  [強制変換] {base_name}{ext} を変換しました！")
                converted = True
                break
            except Exception as e:
                print(f"  [エラー] {old_abs} の変換失敗: {e}")

    # どこにも元画像が無かった場合、どこを探したかを出力する
    if not converted and not os.path.exists(expected_webp_abs):
        print(f"  [消失] 元画像が見つかりません: DBの値={db_path}")
        print(f"         └ 探した場所: {searched_paths[0]}")
    elif not converted and os.path.exists(expected_webp_abs):
        try:
            os.chmod(expected_webp_abs, 0o644)
        except Exception:
            pass

    return expected_webp_rel

# ==========================================
# 3. DB更新パトロール
# ==========================================
def process_rescue(db, target_class, table_label, path_field):
    print(f"\n--- {table_label} の強制パトロールを開始 ---")
    batch_size = 500
    offset = 0
    
    while True:
        items = db.query(target_class).filter(getattr(target_class, path_field) != None).offset(offset).limit(batch_size).all()
        if not items:
            break
            
        for item in items:
            raw_paths = getattr(item, path_field)
            if not raw_paths: continue
            
            column_type = getattr(target_class, path_field).type
            is_json = isinstance(column_type, JSON) or isinstance(raw_paths, list)
            
            needs_update = False
            
            if is_json:
                path_list = raw_paths if isinstance(raw_paths, list) else json.loads(raw_paths)
                new_list = []
                for p in path_list:
                    new_p = force_convert_and_rescue(p)
                    new_list.append(new_p)
                    if p != new_p: needs_update = True
                if needs_update: setattr(item, path_field, new_list)
            else:
                p = raw_paths
                new_p = force_convert_and_rescue(p)
                if p != new_p:
                    needs_update = True
                    setattr(item, path_field, new_p)
                    
        db.commit()
        offset += batch_size

def main():
    print("🚑 画像の強制WebP化＆原因究明スクリプトを起動します")
    print(f"📁 検索ルート: {STORAGE_BASE_PATH}")
    db = SessionLocal()
    try:
        process_rescue(db, Manufacturer, "manufacturers", "local_logo_path")
        process_rescue(db, Shop, "shops", "local_image_path")
        process_rescue(db, BikeModel, "bike_models", "local_image_path")
        process_rescue(db, Listing, "listings", "local_image_paths")
    finally:
        db.close()
    print("\n🎉 パトロール完了！")

if __name__ == "__main__":
    main()