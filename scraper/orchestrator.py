import subprocess
import sys
import time

def run_script(script_name):
    """指定したスクリプトを実行する"""
    print(f"\n{'='*60}")
    print(f" 実行中: {script_name}")
    print(f"{'='*60}")
    
    start_time = time.time()
    
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
    else:
        print(f"\n失敗: {script_name} (エラーコード: {process.returncode})")
        # 重要なステップで失敗した場合は停止させる
        sys.exit(process.returncode)

def main():
    # 実行するスクリプトの順番を定義
    scripts = [
        # 1. 車種マスタの同期 (各サイトのID紐付けを含む)
        "goobike_model_collector.py",
        "bds_model_collector.py",
        
        # 2. 車種名からの排気量データ補完 (即時反映可能なロジック)
        "bike_model_displacement_fixer.py",
        
        # 3. 販売店情報の収集 (出品情報の紐付けに必須)
        "goobike_shop_collector.py",
        "bds_shop_collector.py",
        
        # 4. スクレイピングによる排気量データの詳細補完 (BDS用: 1回取得でスキップ)
        "bds_displacement_collector.py",
        
        # 5. 出品情報の収集 (最終的なメインデータの流し込み)
        "goobike_listing_collector.py",
        "bds_listing_collector.py"
    ]

    print("MotoHub データ一括収集プロセスを開始します...")
    total_start = time.time()

    for script in scripts:
        # スクリプトを順次実行
        run_script(script)

    total_end = time.time()
    print(f"\n{'='*60}")
    print(f" 全プロセス完了！ 合計所要時間: {(total_end - total_start)/60:.2f}分")
    print(f"{'='*60}")

if __name__ == "__main__":
    main()