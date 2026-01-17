import subprocess
import os
import sys
import time

# プロジェクトのルートディレクトリ（scraperフォルダの親）を取得
PROJECT_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

def run_script(script_path):
    """
    指定されたスクリプトをサブプロセスとして実行する
    """
    # 実行するフルパスを構築
    full_path = os.path.join(PROJECT_ROOT, "scraper", script_path)
    
    print(f"\n{'='*60}")
    print(f" 実行中: {script_path}")
    print(f"{'='*60}")
    
    if not os.path.exists(full_path):
        print(f"エラー: ファイルが見つかりません: {full_path}")
        return False

    try:
        # docker-compose環境下で実行されることを想定し、
        # pythonインタープリタで直接スクリプトを叩く
        result = subprocess.run([sys.executable, full_path], check=True)
        return result.returncode == 0
    except subprocess.CalledProcessError as e:
        print(f"スクリプト実行中にエラーが発生しました: {script_path}")
        print(f"終了コード: {e.returncode}")
        return False
    except Exception as e:
        print(f"予期せぬエラーが発生しました: {e}")
        return False

def main():
    # 依存関係を考慮した実行順序（最新のScrapy版ファイル名に対応）
    scripts = [
        # --- STEP 1: マスタデータの作成 (メーカー・車種) ---
        "goobike/model_collector.py",
        "bds/model_collector.py",
        "webike/model_collector.py",
        
        # --- STEP 2: マスタの補完・修正 ---
        "goobike/category_collector.py",
        "bds/category_collector.py",
        "webike/category_collector.py",
        
        # --- STEP 3: 販売店情報の収集 (両サイトともScrapy版) ---
        "goobike/shop_collector.py",
        "bds/shop_collector.py",
        "webike/shop_collector.py",
        
        # --- STEP 4: 詳細スペック（排気量等）の調整 ---
        "bds/displacement_collector.py", 
        
        # --- STEP 5: 出品情報の収集 (両サイトともScrapy版) ---
        "goobike/listing_collector.py",
        "bds/listing_collector.py",
        "webike/listing_collector.py",
        
        # --- STEP 6: 画像のローカル同期 (全テーブル対応版) ---
        "utils/image_syncar.py",
    ]

    start_time = time.time()
    success_count = 0

    for script in scripts:
        if run_script(script):
            success_count += 1
        else:
            print(f"\n[警告] {script} が正常に終了しませんでした。継続しますか？ (y/n)")
            # 非対話環境を考慮し、エラーがあっても停止せずログに残して次へ進みます。
            pass

    end_time = time.time()
    duration = end_time - start_time

    print(f"\n{'#'*60}")
    print(f" 全工程終了")
    print(f" 成功: {success_count} / {len(scripts)}")
    print(f" 総実行時間: {duration/60:.2f} 分")
    print(f"{'#'*60}")

if __name__ == "__main__":
    main()