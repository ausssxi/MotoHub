import subprocess
import os
import sys
import time
import logging

# ==========================================
# 1. パス設定
# ==========================================
# コンテナ内での実行を基準にする (/var/www/scraper/main.py として実行される想定)
# もしローカルで実行する場合も、スクリプトがある場所を基準に相対パスで構築します
SCRAPER_DIR = os.path.dirname(os.path.abspath(__file__))

def run_script(script_path):
    """
    指定されたスクリプトをサブプロセスとして実行する
    """
    full_path = os.path.join(SCRAPER_DIR, script_path)
    
    print(f"\n{'='*60}")
    print(f" 実行中: {script_path}")
    print(f"{'='*60}")
    
    if not os.path.exists(full_path):
        print(f"エラー: ファイルが見つかりません: {full_path}")
        return False

    try:
        # コンテナ内の環境で現在のPythonインタープリタを使用して実行
        # stdout/stderr をそのまま流すことで、Scrapyのログをリアルタイムで表示
        result = subprocess.run([sys.executable, full_path], check=True)
        return result.returncode == 0
    except subprocess.CalledProcessError as e:
        print(f"FAILED: {script_path} (Exit Code: {e.returncode})")
        return False
    except Exception as e:
        print(f"予期せぬエラーが発生しました: {e}")
        return False

def main():
    # 実行順序（依存関係を考慮）
    # ファイル名やディレクトリ構造を最新の状態（横並び構成）に合わせて修正
    scripts = [
        # --- STEP 1: マスタデータの作成 (メーカー・車種) ---
        "goobike/model_collector.py",
        "bds/model_collector.py",
        "webike/model_collector.py",
        
        # --- STEP 2: マスタの補完 (カテゴリー) ---
        "goobike/category_collector.py",
        "bds/category_collector.py",
        # "webike/category_collector.py", # 必要に応じて作成
        
        # --- STEP 3: 販売店情報の収集 ---
        "goobike/shop_collector.py",
        "bds/shop_collector.py",
        "webike/shop_collector.py",
        
        # --- STEP 4: 排気量データの補完 (BDS等の不足分) ---
        "bds/displacement_collector.py", 
        
        # --- STEP 5: 出品情報の収集 (メインのクローリング) ---
        "goobike/listing_collector.py",
        "bds/listing_collector.py",
        "webike/listing_collector.py",
        
        # --- STEP 6: 画像のローカル同期 (最後に実行) ---
        # タイポ修正: image_syncar.py -> image_syncer.py
        "image_syncer.py",
    ]

    start_time = time.time()
    success_count = 0

    print(f"MotoHub Total Crawling Process Started at {time.strftime('%Y-%m-%d %H:%M:%S')}")

    for script in scripts:
        if run_script(script):
            success_count += 1
        else:
            # エラー時も停止せず、次のサイトのクローリングを継続する
            print(f"\n[WARNING] {script} failed. Skipping to next...")

    end_time = time.time()
    duration = end_time - start_time

    print(f"\n{'#'*60}")
    print(f" 全工程終了レポート")
    print(f" 成功数: {success_count} / {len(scripts)}")
    print(f" 総実行時間: {duration/60:.2f} 分")
    print(f"{'#'*60}")

if __name__ == "__main__":
    main()