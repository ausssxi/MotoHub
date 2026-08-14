import unicodedata
import re

# ダッシュ類（ハイフン/マイナス/各種ダッシュ/全角ハイフン）。ASCIIハイフンへ統一する対象。
# ★ U+30FC（長音符「ー」）は絶対に含めない。含めると「モンキー」が「モンキ-」になる。
_DASH_RE = re.compile('[‐‑‒–—―−－-]')

# 照合キー用に落とす区切り: 空白・中黒（U+30FB）・半角中黒（U+FF65）・ASCIIハイフン。
# ここにも U+30FC（長音符）は含めない。
_SEP_RE = re.compile('[\\s・･-]')

def normalize_name(name: str) -> str:
    """表示・保存用。NFKC 正規化に加え、ダッシュ類を ASCII ハイフンへ統一する。

    NFKC は U+FF0D(全角ハイフン)は ASCII 化するが U+2212(MINUS SIGN) や
    U+2010〜U+2015 は変換しないため、車種名に別コードのダッシュが残って重複の原因になる。
    _DASH_RE で ASCII ハイフンへ寄せる。長音符 U+30FC は対象外（車種名を壊さない）。
    """
    if not name:
        return ""
    s = unicodedata.normalize('NFKC', name)
    s = _DASH_RE.sub('-', s)
    s = re.sub(r'\s+', ' ', s)
    return s.strip().lower()

def model_match_key(name: str) -> str:
    """照合用。空白・中黒・ハイフンを落とした比較キー。

    表示名の区切り差（「タクト・ベーシック」/「タクトベーシック」、
    「v−ストローム250」/「vストローム250」）を無視して同一車種を突き合わせるためのもの。
    表示・保存には使わず、既存 bike_models との照合にのみ使う。
    """
    return _SEP_RE.sub('', normalize_name(name))

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

    # マッピング辞書（endswith判定より先に照合する。
    # '京都'は末尾が'都'のため、先にendswithで素通りさせると'京都府'に統一されないため）
    mapping = {
        '東京': '東京都', '京都': '京都府', '大阪': '大阪府', '北海道': '北海道'
    }

    if pref in mapping:
        return mapping[pref]

    # すでに正式名称ならそのまま返す
    if pref.endswith(('都', '道', '府', '県')):
        return pref

    # それ以外（県）は末尾に「県」を付与
    # (ただし、念のため実在する県名のリストに含まれる場合のみなど、ガードを入れるのが理想)
    return pref + '県'