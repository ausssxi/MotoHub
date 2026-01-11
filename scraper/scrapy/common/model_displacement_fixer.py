import os
import re
import unicodedata
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# 環境変数の読み込み
load_dotenv()
if not os.getenv("DB_DATABASE"):
    load_dotenv(dotenv_path='../.env')

# データベース接続設定
user = os.getenv("DB_USERNAME", "sail")
password = os.getenv("DB_PASSWORD", "password")
host = os.getenv("DB_HOST", "db")
port = os.getenv("DB_PORT", "3306")
database = os.getenv("DB_DATABASE", "motohub")
DATABASE_URL = f"mysql+pymysql://{user}:{password}@{host}:{port}/{database}"

engine = create_engine(DATABASE_URL)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

class Base(DeclarativeBase):
    pass

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True, index=True)
    name = Column(String(255), nullable=False)
    displacement = Column(Integer, nullable=True)

def normalize_text(text):
    """全角英数字を半角に変換する"""
    if not text:
        return ""
    return unicodedata.normalize('NFKC', text)

def extract_displacement(name):
    """名前から50以上の数値を抽出する"""
    normalized_name = normalize_text(name)
    # 文字列内の数値をすべて抽出
    numbers = re.findall(r'\d+', normalized_name)
    
    for num_str in numbers:
        num = int(num_str)
        # 50以上かつ2500未満（年式2024年などと誤爆しないための緩いガード）
        # ただし、400, 250, 750 などの代表的な排気量を優先的に拾う仕組み
        if 50 <= num < 2500:
            # 2000年代の年式(2000-2026)と思われるものは除外するロジック
            if 1990 <= num <= 2030:
                continue
            return num
    return None

def fix_displacements():
    db = SessionLocal()
    print("車種名からの排気量補完プロセスを開始します...")
    
    try:
        # displacement が NULL または 0 のものを取得
        targets = db.query(BikeModel).filter(
            (BikeModel.displacement == None) | (BikeModel.displacement == 0)
        ).all()
        
        updated_count = 0
        for model in targets:
            suggested_val = extract_displacement(model.name)
            
            if suggested_val:
                model.displacement = suggested_val
                updated_count += 1
                print(f"  [UPDATE] {model.name} -> {suggested_val}cc")
        
        db.commit()
        print(f"\n完了: {updated_count} 件の排気量を更新しました。")

    except Exception as e:
        print(f"エラーが発生しました: {e}")
        db.rollback()
    finally:
        db.close()

if __name__ == "__main__":
    fix_displacements()