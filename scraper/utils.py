import unicodedata
import re

def normalize_name(name: str) -> str:
    """バイク車種名などの文字列を正規化する"""
    if not name: return ""
    normalized = unicodedata.normalize('NFKC', name)
    return normalized.strip().lower()

def normalize_shop_name(name: str) -> str:
    """
    店舗名の名寄せ用正規化
    括弧の除去、記号の除去、法人格の除去を行い、純粋な店名のみを抽出する
    """
    if not name: return ""
    name = unicodedata.normalize('NFKC', name)
    
    # 1. 括弧とその中身を削除 (例: (有)三田商会 -> 三田商会 / ミタモータース(福岡店) -> ミタモータース)
    name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', name)

    # 2. バイク業界で頻出するカタカナ語を英語に変換（名寄せの精度向上）
    trans_map = {
        "アウトレット": "outlet", "モーター": "motor", "サイクル": "cycle",
        "ショップ": "shop", "センター": "center", "ファクトリー": "factory",
        "ガレージ": "garage", "ワークス": "works", "サービス": "service",
        "モータース": "motors", "カスタム": "custom",
    }
    for kana, eng in trans_map.items():
        name = name.replace(kana, eng)

    # 3. 法人格や不要なキーワードを除去
    noise = ["株式会社", "有限会社", "合資会社", "合同会社", "株", "有", "店", "販売店"]
    for word in noise:
        name = name.replace(word, "")

    # 4. 記号と空白をすべて除去
    name = re.sub(r'[^a-z0-9\u4e00-\u9faf\u3040-\u309f\u30a0-\u30ff]', '', name.lower())
    
    return name.strip()

def normalize_address(addr: str) -> str:
    """住所の名寄せ用正規化（番地のハイフン統一など）"""
    if not addr: return ""
    addr = unicodedata.normalize('NFKC', addr)
    # 数字間のハイフンなどを統一
    addr = addr.replace('丁目', '-').replace('番地', '-').replace('番', '-').replace('号', '')
    addr = re.sub(r'[－ー−―‐－-]', '-', addr)
    addr = re.sub(r'-+', '-', addr)
    # 空白除去
    addr = re.sub(r'\s+', '', addr)
    return addr.strip('-')

def normalize_phone(phone: str) -> str:
    """電話番号の数字のみを抽出"""
    if not phone: return ""
    return re.sub(r'\D', '', phone)

def extract_displacement(name: str) -> int:
    """車種名から排気量を推測する共通ロジック"""
    if not name: return None
    cc_match = re.search(r'(\d+)\s*(?:cc|cm3)', name, re.IGNORECASE)
    if cc_match: return int(cc_match.group(1))
    numbers = re.findall(r'\d+', name)
    for num_str in numbers:
        num = int(num_str)
        if 1900 <= num <= 2100: continue
        if 49 <= num <= 2500: return num
    return None

def normalize_prefecture(pref: str) -> str:
    """
    都道府県名を正式名称に正規化する
    例: '東京' -> '東京都', '神奈川' -> '神奈川県'
    """
    if not pref: return ""
    pref = pref.strip()
    
    # すでに正式名称ならそのまま返す
    if pref.endswith(('都', '道', '府', '県')):
        return pref

    # マッピング辞書
    mapping = {
        '東京': '東京都', '京都': '京都府', '大阪': '大阪府', '北海道': '北海道'
    }
    
    if pref in mapping:
        return mapping[pref]
    
    # それ以外（県）は末尾に「県」を付与
    # (ただし、念のため実在する県名のリストに含まれる場合のみなど、ガードを入れるのが理想)
    return pref + '県'