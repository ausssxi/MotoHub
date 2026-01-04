import subprocess
import sys
import time
import os

def run_script(script_name):
    """指定したスクリプトを外部プロセスとして実行する"""
    print(f"\n{'='*60}")
    print(f" 実行中: {script_name}")
    print(f"{'='*60}")
    
    start_time = time.time()
    
    # ファイルが存在するか確認
    if not os.path.exists(script_name):
        print(f"エラー: {script_name} が見つかりません。スキップします。")
        return False

    # 外部プロセスとして実行
    process = subprocess.Popen(
        [sys.executable, script_name],
        stdout=sys.stdout,
        stderr=sys.stderr
    )
    process.wait()
    
    end_time = time.time()
    duration = end_time - start_time
    
    if process.returncode == 0:
        print(f"\n成功: {script_name} (所要時間: {duration:.2f}秒)")
        return True
    else:
        print(f"\n失敗: {script_name} (エラーコード: {process.returncode})")
        # 重要なマスタ作成ステップで失敗した場合は、後続のデータ不整合を防ぐため停止させる
        if "collector" in script_name and "listing" not in script_name:
            print("マスタデータの収集に失敗したため、プロセスを中断します。")
            sys.exit(process.returncode)
        return False

def main():
    # 実行するスクリプトの最適な順番
    scripts = [
        # --- STEP 1: マスタデータの作成 ---
        "goobike/model_collector.py",
        "bds/model_collector.py",
        
        # --- STEP 2: マスタの補完・修正 ---
        "common/bike_model_displacement_fixer.py",
        "goobike/category_collector.py",
        "bds/category_collector.py",
        
        # --- STEP 3: 販売店情報の収集と地理情報の付与 ---
        "goobike/shop_collector.py",
        "bds/shop_collector.py",
        # "common/geocoding_service.py", # APIキー取得後に有効化を推奨
        
        # --- STEP 4: 詳細スペックの深掘り収集 ---
        "bds/displacement_fixer.py", 
        
        # --- STEP 5: 出品情報の収集 ---
        "goobike/listing_collector.py",
        "bds/listing_collector.py",
        
        # --- STEP 6: 画像のローカル同期 (UIに必須) ---
        "common/image_downloader.py",
    ]

    print("MotoHub データ収集パイプラインを開始します...")
    total_start = time.time()

    for script in scripts:
        run_script(script)

    total_end = time.time()
    print(f"\n{'='*60}")
    print(f" 全プロセス完了！ 合計所要時間: {(total_end - total_start)/60:.2f}分")
    print(f"{'='*60}")

if __name__ == "__main__":
    main()