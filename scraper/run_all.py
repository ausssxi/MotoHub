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
        # --- STEP 1: 車種マスタの作成とID紐付け ---
        "goobike_model_collector.py",
        "bds_model_collector.py",
        
        # --- STEP 2: マスタ情報の補完 (カテゴリー/名前からの排気量) ---
        "bike_model_displacement_fixer.py",  # 車種名から数値を抽出して更新
        "goobike_category_collector.py",      # ジャンルページからスタイルを紐付け
        "bds_category_collector.py",          # スタイル別ページからスタイルを紐付け
        
        # --- STEP 3: 販売店情報の収集 ---
        "goobike_shop_collector.py",
        "bds_shop_collector.py",
        
        # --- STEP 4: 詳細スペックの深掘り収集 ---
        "bds_displacement_collector.py",      # 車両個別ページから正確な排気量を収集
        
        # --- STEP 5: 出品情報の収集 ---
        "goobike_listing_collector.py",
        "bds_listing_collector.py"
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