import mysql.connector
import requests
from bs4 import BeautifulSoup
import time
import random
import re
import unicodedata
import os
import sys

# ==========================================
# .env ファイルから設定を読み込む関数
# ==========================================
def load_laravel_env():
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
            from dotenv import load_dotenv
            load_dotenv(target_env)
        except ImportError:
            with open(target_env, 'r', encoding='utf-8') as f:
                for line in f:
                    line = line.strip()
                    if not line or line.startswith('#') or '=' not in line:
                        continue
                    key, value = line.split('=', 1)
                    if key.startswith('export '):
                        key = key.replace('export ', '')
                    os.environ[key.strip()] = value.strip().strip("'").strip('"')
    else:
        print("⚠️ .env ファイルが見つかりませんでした。")

load_laravel_env()

# ==========================================
# データベース接続設定
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
# メーカー名マッピング (MotoHubの日本語 -> バイクブロスのメーカーID)
# ==========================================
MAKER_MAP = {
    'ホンダ': '1',
    'ヤマハ': '2',
    'スズキ': '3',
    'カワサキ': '4',
    'ハーレーダビッドソン': '5',
    'BMW': '9',
    'ドゥカティ': '18',
    'アプリリア': '20',
    'トライアンフ': '15',
    'KTM': '37',
    'ベスパ': '34',
    'キムコ': '75',
    'PGO': '74',
}

catalog_cache = {}

# ==========================================
# URL自動探索ロジック (バイクブロス用)
# ==========================================
def get_bikebros_catalog_links(maker_id):
    if maker_id in catalog_cache:
        return catalog_cache[maker_id]
        
    url = f"https://www.bikebros.co.jp/catalog/{maker_id}/"
    print(f"  [探索] バイクブロスのカタログ一覧(ID:{maker_id})を取得中...")
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    }
    try:
        res = requests.get(url, headers=headers, timeout=10)
        res.raise_for_status()
        res.encoding = res.apparent_encoding
        soup = BeautifulSoup(res.text, 'html.parser')
        
        links = {}
        for a in soup.find_all('a', href=True):
            href = a['href']
            # バイクブロスのカタログURLフォーマット (例: /catalog/2/71_5/) を抽出
            if re.match(rf'^/catalog/{maker_id}/[0-9a-zA-Z_]+/$', href):
                text = unicodedata.normalize('NFKC', a.text.strip()).upper()
                if text and text not in ["", "新車", "中古車", "カタログ"]:
                    full_url = "https://www.bikebros.co.jp" + href
                    links[text] = full_url
        
        catalog_cache[maker_id] = links
        time.sleep(random.uniform(1.0, 2.0))
        return links
    except Exception as e:
        print(f"  [探索エラー] カタログ一覧の取得に失敗: {e}")
        return {}

def find_detail_url(maker_name, model_name):
    if not maker_name:
        return None
        
    maker_id = MAKER_MAP.get(maker_name)
    if not maker_id:
        return None
        
    links = get_bikebros_catalog_links(maker_id)
    if not links:
        return None
    
    normalized_name = unicodedata.normalize('NFKC', model_name.strip()).upper()
    name_no_space = normalized_name.replace(' ', '').replace('　', '').replace('-', '').replace('/', '')
    
    if normalized_name in links:
        return links[normalized_name]
        
    for text, url in links.items():
        text_no_space = text.replace(' ', '').replace('　', '').replace('-', '').replace('/', '')
        if name_no_space == text_no_space:
            return url
            
    for text, url in links.items():
        text_no_space = text.replace(' ', '').replace('　', '').replace('-', '').replace('/', '')
        if name_no_space in text_no_space or text_no_space in name_no_space:
            return url
            
    return None

# ==========================================
# データ抽出用ヘルパー関数
# ==========================================
def extract_number(text):
    if not text or text in ['-', '―', '不明']:
        return None
    match = re.search(r'\d+', text.replace(',', ''))
    return int(match.group()) if match else None

def extract_float(text):
    if not text or text in ['-', '―', '不明']:
        return None
    match = re.search(r'\d+(\.\d+)?', text.replace(',', ''))
    return float(match.group()) if match else None

def get_clean_val(specs_dict, key):
    val = specs_dict.get(key)
    if not val or val in ['-', '―', '不明']:
        return None
    return val

# 複数の項目を組み合わせて美しい「エンジン種類」テキストを生成
def build_engine_type(specs):
    cooling = specs.get('冷却方式', '')
    cycle = specs.get('原動機種類', '')
    cam = specs.get('カム・バルブ駆動方式', '')
    valve = specs.get('気筒あたりバルブ数', '')
    cylinder = specs.get('シリンダ配列', '')
    
    parts = [cooling, cycle, cam]
    if valve and valve.isdigit():
        parts.append(f"{valve}バルブ")
    parts.append(cylinder)
    
    res = " ".join([p for p in parts if p]).strip()
    return res if res else None

# 出力を結合 (例: 13kW(18PS)/7500rpm)
def build_power(specs):
    kw = specs.get('最高出力（kW）')
    ps = specs.get('最高出力（PS）')
    rpm = specs.get('最高出力回転数（rpm）')
    if kw and ps and rpm:
        return f"{kw}kW({ps}PS)/{rpm}rpm"
    elif ps and rpm:
        return f"{ps}PS/{rpm}rpm"
    return None

# トルクを結合 (例: 18N・m(1.8kgf・m)/6000rpm)
def build_torque(specs):
    nm = specs.get('最大トルク（N・m）')
    kgm = specs.get('最大トルク（kgf･m）')
    rpm = specs.get('最大トルク回転数（rpm）')
    if nm and kgm and rpm:
        return f"{nm}N・m({kgm}kgf・m)/{rpm}rpm"
    elif kgm and rpm:
        return f"{kgm}kgf・m/{rpm}rpm"
    return None

# ==========================================
# スクレイピング実行関数
# ==========================================
def fetch_bikebros_specs(maker_name, model_name):
    print(f"🔍 [{maker_name} {model_name}] のスペックを探索中...")
    
    detail_url = find_detail_url(maker_name, model_name)
    
    if not detail_url:
        print(f"  └ ⚠️ バイクブロス内に一致するカタログが見つかりませんでした。")
        return None
        
    print(f"  └ 🔗 カタログ発見: {detail_url}")

    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    }

    try:
        response = requests.get(detail_url, headers=headers, timeout=10)
        response.raise_for_status()
        response.encoding = response.apparent_encoding
        soup = BeautifulSoup(response.text, 'html.parser')
        
        # 1. テーブルから全データを辞書化する（最強の抽出方法）
        specs_raw = {}
        for tr in soup.find_all('tr'):
            th = tr.find('th')
            td = tr.find('td')
            if th and td:
                specs_raw[th.text.strip()] = td.text.strip()
        
        # 2. 辞書からDBのカラムに合わせてデータを成形する
        specs = {
            'model_code': get_clean_val(specs_raw, '型式'),
            'release_year': extract_number(specs_raw.get('発売年')),
            'length': extract_number(specs_raw.get('全長 (mm)')),
            'width': extract_number(specs_raw.get('全幅 (mm)')),
            'height': extract_number(specs_raw.get('全高 (mm)')),
            'seat_height': extract_number(specs_raw.get('シート高 (mm)')),
            'weight': extract_number(specs_raw.get('車両重量 (kg)')) or extract_number(specs_raw.get('乾燥重量 (kg)')),
            
            'engine_type': build_engine_type(specs_raw),
            'displacement': extract_number(specs_raw.get('排気量 (cc)')),
            'fuel_consumption': extract_float(specs_raw.get('燃料消費率（1）(km/L)')) or extract_float(specs_raw.get('燃料消費率（2）(km/L)')),
            'tank_capacity': extract_float(specs_raw.get('燃料タンク容量 (L)')),
            'fuel_supply': get_clean_val(specs_raw, '燃料供給方式'),
            
            'max_power': build_power(specs_raw),
            'max_torque': build_torque(specs_raw),
            
            'tire_size_front': get_clean_val(specs_raw, 'タイヤ（前）'),
            'tire_size_rear': get_clean_val(specs_raw, 'タイヤ（後）'),
            'brake_type_front': get_clean_val(specs_raw, 'ブレーキ形式（前）'),
            'brake_type_rear': get_clean_val(specs_raw, 'ブレーキ形式（後）'),
        }

        return specs

    except Exception as e:
        print(f"❌ エラー発生 ({model_name}): {e}")
        return None

# ==========================================
# メイン処理
# ==========================================
def main():
    print("🚀 【バイクブロス版】 全車種カタログスペック収集バッチを開始します（全自動ループ版）...")
    
    hidden_pass = "***" if DB_CONFIG['password'] else "NONE"
    print(f"📡 接続先情報: {DB_CONFIG['user']}@{DB_CONFIG['host']}:{DB_CONFIG['port']} (DB: {DB_CONFIG['database']}, PASS: {hidden_pass})")
    
    conn = None
    cursor = None
    
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor(dictionary=True)
        
        last_processed_id = 0
        total_processed = 0

        while True:
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
                
                last_processed_id = model_id
                
                # バイクブロスからデータを取得
                specs = fetch_bikebros_specs(maker_name, model_name)
                
                if specs:
                    update_query = """
                        UPDATE bike_models 
                        SET model_code = %(model_code)s,
                            release_year = %(release_year)s,
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
                    print(f"  └ ⏭️ {model_name} はスキップされました。\n")
                
                total_processed += 1
                
                # BAN防止：適度なスリープ
                sleep_time = random.uniform(2.0, 4.0) 
                time.sleep(sleep_time)
                
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