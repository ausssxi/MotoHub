import mysql.connector
import requests
from bs4 import BeautifulSoup
import time
import random
import re
import unicodedata
import os
import sys

# common パッケージ（正直な User-Agent 定義）を import できるよう scraper ルートを検索パスに追加
_scraper_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
if _scraper_root not in sys.path:
    sys.path.append(_scraper_root)
from common.user_agent import MOTOHUB_USER_AGENT

# ==========================================
# .env ファイルから設定を読み込む関数 (完全互換版)
# ==========================================
def load_laravel_env():
    """他のバッチスクリプトと同様に確実に .env を読み込む"""
    env_paths = [
        '/var/www/.env',  # Dockerコンテナ内の絶対パス（最優先）
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../.env')),
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../backend/.env')),
        '.env'
    ]
    
    target_env = None
    for p in env_paths:
        if os.path.exists(p):
            target_env = p
            break

    if target_env:
        print(f"✅ .env ファイルを読み込みます: {target_env}")
        try:
            # 他のスクリプトで使われているかもしれない dotenv を優先
            from dotenv import load_dotenv
            load_dotenv(target_env)
        except ImportError:
            # ライブラリが無い環境でも強制的にパースするフォールバック
            with open(target_env, 'r', encoding='utf-8') as f:
                for line in f:
                    line = line.strip()
                    if not line or line.startswith('#') or '=' not in line:
                        continue
                    key, value = line.split('=', 1)
                    # 万が一 export などの文字が入っていても除去
                    if key.startswith('export '):
                        key = key.replace('export ', '')
                    os.environ[key.strip()] = value.strip().strip("'").strip('"')
    else:
        print("⚠️ .env ファイルが見つかりませんでした。")

# 実行して環境変数をセット
load_laravel_env()

# ==========================================
# データベース接続設定 (.env から取得)
# ==========================================
DB_CONFIG = {
    'host': os.environ.get('DB_HOST', 'db'),
    'port': int(os.environ.get('DB_PORT', 3306)),
    'user': os.environ.get('DB_USERNAME', 'root'),
    'password': os.environ.get('DB_PASSWORD', ''),
    'database': os.environ.get('DB_DATABASE', 'motohub'),
    'charset': 'utf8mb4'
}

# ==========================================
# メーカー名マッピング (MotoHubの日本語 -> GooBikeのディレクトリ名)
# ==========================================
MAKER_MAP = {
    'ホンダ': 'HONDA',
    'ヤマハ': 'YAMAHA',
    'スズキ': 'SUZUKI',
    'カワサキ': 'KAWASAKI',
    'ハーレーダビッドソン': 'HARLEY_DAVIDSON',
    'BMW': 'BMW',
    'ドゥカティ': 'DUCATI',
    'KTM': 'KTM',
    'アプリリア': 'APRILIA',
    'トライアンフ': 'TRIUMPH',
    'ベスパ': 'VESPA',
    'キムコ': 'KYMCO',
    'SYM': 'SYM',
    'ハスクバーナ': 'HUSQVARNA',
}

catalog_cache = {}

# ==========================================
# URL自動探索ロジック
# ==========================================
def get_goobike_catalog_links(maker_en):
    if maker_en in catalog_cache:
        return catalog_cache[maker_en]
        
    url = f"https://www.goobike.com/catalog/{maker_en}/index.html"
    print(f"  [探索] {maker_en} のカタログ一覧を取得中...")
    headers = {
        'User-Agent': MOTOHUB_USER_AGENT,
    }
    try:
        res = requests.get(url, headers=headers, timeout=10)
        res.raise_for_status()
        res.encoding = res.apparent_encoding
        soup = BeautifulSoup(res.text, 'html.parser')
        
        links = {}
        for a in soup.find_all('a', href=True):
            href = a['href']
            if f"/catalog/{maker_en}/" in href and href.endswith('/index.html'):
                if href == f"/catalog/{maker_en}/index.html":
                    continue
                text = unicodedata.normalize('NFKC', a.text.strip()).upper()
                if text:
                    full_url = "https://www.goobike.com" + href if href.startswith('/') else href
                    links[text] = full_url
        
        catalog_cache[maker_en] = links
        time.sleep(random.uniform(1.0, 2.0))
        return links
    except Exception as e:
        print(f"  [探索エラー] カタログ一覧の取得に失敗: {e}")
        return {}

def find_detail_url(maker_name, model_name):
    if not maker_name:
        return None
        
    maker_en = MAKER_MAP.get(maker_name)
    if not maker_en:
        return None
        
    links = get_goobike_catalog_links(maker_en)
    if not links:
        return None
    
    normalized_name = unicodedata.normalize('NFKC', model_name.strip()).upper()
    name_no_space = normalized_name.replace(' ', '').replace('　', '').replace('-', '')
    
    if normalized_name in links:
        return links[normalized_name]
        
    for text, url in links.items():
        text_no_space = text.replace(' ', '').replace('　', '').replace('-', '')
        if name_no_space == text_no_space:
            return url
            
    for text, url in links.items():
        text_no_space = text.replace(' ', '').replace('　', '').replace('-', '')
        if name_no_space in text_no_space or text_no_space in name_no_space:
            return url
            
    return None

# ==========================================
# データ抽出用ヘルパー関数
# ==========================================
def extract_int(soup, element_id):
    element = soup.find('td', id=element_id)
    if element:
        text = element.text.strip()
        match = re.search(r'\d+', text.replace(',', ''))
        if match:
            return int(match.group())
    return None

def extract_float(soup, element_id):
    element = soup.find('td', id=element_id)
    if element:
        text = element.text.strip()
        match = re.search(r'\d+(\.\d+)?', text.replace(',', ''))
        if match:
            return float(match.group())
    return None

def extract_str(soup, element_id):
    element = soup.find('td', id=element_id)
    if element:
        text = element.text.strip()
        if text and text not in ['-', '―', '不明']:
            return text
    return None

# ==========================================
# スクレイピング実行関数
# ==========================================
def fetch_goobike_specs(maker_name, model_name):
    print(f"🔍 [{maker_name} {model_name}] のスペックを探索中...")
    
    detail_url = find_detail_url(maker_name, model_name)
    
    if not detail_url:
        print(f"  └ ⚠️ GooBike内に一致するカタログが見つかりませんでした。")
        return None
        
    print(f"  └ 🔗 カタログ発見: {detail_url}")

    headers = {
        'User-Agent': MOTOHUB_USER_AGENT,
        'Accept-Language': 'ja,en-US;q=0.9,en;q=0.8',
    }

    try:
        response = requests.get(detail_url, headers=headers, timeout=10)
        response.raise_for_status()
        response.encoding = response.apparent_encoding
        
        soup = BeautifulSoup(response.text, 'html.parser')
        
        specs = {
            'model_code': extract_str(soup, 'katashiki'),
            'length': extract_int(soup, 'length'),
            'width': extract_int(soup, 'width'),
            'height': extract_int(soup, 'height'),
            'seat_height': extract_int(soup, 'sheet_height'),
            'weight': extract_int(soup, 'weight'),
            'engine_type': extract_str(soup, 'engine'),
            'displacement': extract_int(soup, 'haiki_cc'),
            'tank_capacity': extract_float(soup, 'tank'),
            'fuel_supply': extract_str(soup, 'b_fuel_put'),
            'max_power': extract_str(soup, 'max_power_display'),
            'max_torque': extract_str(soup, 'max_torque_display'),
            'tire_size_front': extract_str(soup, 'f_tire_size'),
            'tire_size_rear': extract_str(soup, 'r_tire_size'),
            'brake_type_front': extract_str(soup, 'f_brake'),
            'brake_type_rear': extract_str(soup, 'r_brake')
        }
        specs['fuel_consumption'] = None

        return specs

    except Exception as e:
        print(f"❌ エラー発生 ({model_name}): {e}")
        return None

# ==========================================
# メイン処理
# ==========================================
def main():
    print("🚀 GooBike 全車種カタログスペック収集バッチを開始します（全自動ループ版）...")
    
    hidden_pass = "***" if DB_CONFIG['password'] else "NONE"
    print(f"📡 接続先情報: {DB_CONFIG['user']}@{DB_CONFIG['host']}:{DB_CONFIG['port']} (DB: {DB_CONFIG['database']}, PASS: {hidden_pass})")
    
    conn = None
    cursor = None
    
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor(dictionary=True)
        
        last_processed_id = 0  # スキップされたものを無視して進むための変数
        total_processed = 0

        while True:
            # 前回処理したIDより大きいものを100件取得
            cursor.execute("""
                SELECT bm.id, bm.name AS model_name, m.name AS maker_name 
                FROM bike_models bm
                LEFT JOIN manufacturers m ON bm.manufacturer_id = m.id
                WHERE bm.seat_height IS NULL 
                  AND m.name IS NOT NULL
                  AND bm.id > %s
                ORDER BY bm.id ASC
                LIMIT 100
            """, (last_processed_id,))
            
            target_models = cursor.fetchall()
            
            if not target_models:
                print(f"✅ すべての車種({total_processed}件)の探索が完了しました！")
                break

            print(f"🎯 新たに {len(target_models)}件 の処理を開始します。")

            for row in target_models:
                model_id = row['id']
                model_name = row['model_name']
                maker_name = row['maker_name']
                
                # 処理したIDを記録（次回のスタート地点にする）
                last_processed_id = model_id
                
                specs = fetch_goobike_specs(maker_name, model_name)
                
                if specs:
                    update_query = """
                        UPDATE bike_models 
                        SET model_code = %(model_code)s,
                            length = %(length)s,
                            width = %(width)s,
                            height = %(height)s,
                            seat_height = %(seat_height)s,
                            weight = %(weight)s,
                            engine_type = %(engine_type)s,
                            displacement = %(displacement)s,
                            fuel_consumption = %(fuel_consumption)s,
                            tank_capacity = %(tank_capacity)s,
                            fuel_supply = %(fuel_supply)s,
                            max_power = %(max_power)s,
                            max_torque = %(max_torque)s,
                            tire_size_front = %(tire_size_front)s,
                            tire_size_rear = %(tire_size_rear)s,
                            brake_type_front = %(brake_type_front)s,
                            brake_type_rear = %(brake_type_rear)s,
                            updated_at = NOW()
                        WHERE id = %(id)s
                    """
                    specs['id'] = model_id
                    cursor.execute(update_query, specs)
                    conn.commit()
                    print(f"  └ ✅ {model_name} のスペックを保存しました！\n")
                else:
                    # 見つからなかった場合は何も更新せず、ログだけ出す
                    # (last_processed_id が進むので、次回はこの車種に引っかかりません)
                    print(f"  └ ⏭️ {model_name} はスキップされました。\n")
                
                total_processed += 1
                
                # BAN防止：適度なスリープ
                sleep_time = random.uniform(2.0, 4.0) 
                time.sleep(sleep_time)
                
            # 100件処理ごとにコンソールの見栄えのために少し休憩
            print(f"💤 100件のブロックが完了。5秒間休憩します... (現在計 {total_processed}件 処理済)")
            time.sleep(5)

    except mysql.connector.Error as err:
        print(f"❌ データベースエラー: {err}")
    except KeyboardInterrupt:
        print(f"\n🛑 手動で停止されました。")
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()
            print("🔌 データベース接続を閉じました。")

if __name__ == "__main__":
    main()