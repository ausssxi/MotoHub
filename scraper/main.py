import subprocess
import os
import sys
import time
import logging

# ==========================================
# 1. パス設定
# ==========================================
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
        # リアルタイムでログを表示
        result = subprocess.run([sys.executable, full_path], check=True)
        return result.returncode == 0
    except subprocess.CalledProcessError as e:
        print(f"FAILED: {script_path} (Exit Code: {e.returncode})")
        return False
    except Exception as e:
        print(f"予期せぬエラーが発生しました: {e}")
        return False

def main():
    # 実行順序
    # 各コレクターが pipelines.py を通じて画像保存を行うようになったため、
    # 最後に一括同期する工程は不要になりました。
    scripts = [
        # --- STEP 1: マスタデータの作成 (メーカー・車種) ---
        # ※ 内部で MotoHubImagePipeline が走り、カタログ画像を保存します
        "goobike/model_collector.py",
        "bds/model_collector.py",
        "webike/model_collector.py",
        
        # --- STEP 2: マスタの補完 (カテゴリー) ---
        "goobike/category_collector.py",
        "bds/category_collector.py",
        
        # --- STEP 3: 販売店情報の収集 ---
        # ※ 内部で MotoHubImagePipeline が走り、店舗外観画像を保存します
        "goobike/shop_collector.py",
        "bds/shop_collector.py",
        "webike/shop_collector.py",
        
        # --- STEP 4: 排気量データの補完 ---
        "bds/displacement_collector.py", 
        
        # --- STEP 5: 出品情報の収集 (メイン) ---
        # ※ 内部で MotoHubImagePipeline が走り、バイクの出品写真を保存します
        "goobike/listing_collector.py",
        "bds/listing_collector.py",
        "webike/listing_collector.py",
    ]

    # --- (参考) image_syncer.py について ---
    # ネットワークエラー等で漏れた画像を補完したい場合は、
    # 以下のコマンドを個別に実行してください。
    # python common/image_syncer.py

    start_time = time.time()
    success_count = 0

    print(f"MotoHub Total Crawling Process Started at {time.strftime('%Y-%m-%d %H:%M:%S')}")

    for script in scripts:
        if run_script(script):
            success_count += 1
        else:
            print(f"\n[WARNING] {script} failed. Skipping to next...")

    end_time = time.time()
    duration = end_time - start_time

    print(f"\n{'#'*60}")
    print(f" 全工程終了レポート")
    print(f" 成功数: {success_count} / {len(scripts)}")
    print(f" 総実行時間: {duration/60:.2f} 分")
    print(f" ※ 画像保存は各コレクター内で並列処理されました。")
    print(f"{'#'*60}")

if __name__ == "__main__":
    main()