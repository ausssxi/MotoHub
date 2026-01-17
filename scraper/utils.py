import unicodedata
import re

def normalize_name(name: str) -> str:
    """
    バイク車種名などの文字列を正規化する。
    """
    if not name:
        return ""
    normalized = unicodedata.normalize('NFKC', name)
    return normalized.strip().lower()

def extract_displacement(name: str) -> int:
    """
    車種名から排気量を推測する共通ロジック。
    - 125cc などの単位付きを優先。
    - 年式（1900-2100）を除外して50-2500の数値を拾う。
    """
    if not name:
        return None
    
    # 1. 単位付き（例: 125cc, 400cm3）を最優先で探す
    cc_match = re.search(r'(\d+)\s*(?:cc|cm3)', name, re.IGNORECASE)
    if cc_match:
        return int(cc_match.group(1))

    # 2. 数値をすべて抽出
    numbers = re.findall(r'\d+', name)
    for num_str in numbers:
        num = int(num_str)
        # 年式と思われる4桁はスキップ
        if 1900 <= num <= 2100:
            continue
        # 一般的なバイクの排気量範囲（50cc〜2500cc）
        if 49 <= num <= 2500:
            return num
            
    return None