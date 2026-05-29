"""
都道府県正規化のローカル検証スクリプト（実データ非破壊）。

実行（Dockerコンテナ内）:
    docker compose exec -T app python3 /var/scraper/test_prefecture_normalization.py

検証内容:
  1. normalize_prefecture() の各パターン（理想値と突合）
  2. ShopManager.get_or_create_shop() に短縮形を渡したときの保存値
     （実MySQLを使うが、常に ROLLBACK するため実データは一切変更されない）
  3. fix_shops.py の正規化（住所→正規表現抽出→normalize_prefecture）の関数レベル検証
"""
import os
import sys

current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.append(current_dir)

import re

from utils import normalize_prefecture
from common.database import SessionLocal, Shop
from common.shop_manager import ShopManager

PASS = "\033[92mPASS\033[0m"
FAIL = "\033[91mFAIL\033[0m"

failures = []  # (section, label, actual, expected)


def check(section, label, actual, expected):
    ok = actual == expected
    if not ok:
        failures.append((section, label, actual, expected))
    status = PASS if ok else FAIL
    print(f"  [{status}] {label}: 結果={actual!r} 期待={expected!r}")


# ==========================================
# 1. normalize_prefecture() の各パターン
# ==========================================
def test_normalize_function():
    print("\n=== 1. normalize_prefecture() 関数テスト（理想値と突合） ===")
    cases = [
        # (入力, 理想の期待値)
        ("京都", "京都府"),
        ("東京", "東京都"),
        ("大阪", "大阪府"),
        ("北海道", "北海道"),       # 既に道、素通り
        ("神奈川", "神奈川県"),     # 県付与
        ("愛知", "愛知県"),
        ("京都府", "京都府"),       # 既にフルネーム、素通り
        ("東京都", "東京都"),
        ("神奈川県", "神奈川県"),
        ("沖縄県", "沖縄県"),
        ("", ""),                  # 空文字
        (None, ""),                # None
        ("  京都  ", "京都府"),     # 前後空白
    ]
    for raw, expected in cases:
        check("normalize", f"{raw!r}", normalize_prefecture(raw), expected)


# ==========================================
# 2. get_or_create_shop() の保存検証（実MySQL・常にROLLBACK）
# ==========================================
def test_get_or_create_shop():
    print("\n=== 2. ShopManager.get_or_create_shop() 保存検証（実MySQL・ROLLBACKで非破壊） ===")
    print("  ※ commit せず最後に必ず rollback します。実データは一切変更されません。")

    db = SessionLocal()
    try:
        manager = ShopManager(db)

        # 衝突回避のため、実在しないテスト専用の店名・電話番号を使用。
        # phone=None にして電話番号ベースの名寄せが実店舗に当たらないようにする。
        # (a) 短縮形「神奈川」→ 神奈川県 で保存されること（正常系の代表）
        shop_b = manager.get_or_create_shop(
            site_id=1,
            site_shop_id="__test__shop_kanagawa",
            data={
                "name": "__テスト__神奈川店",
                "prefecture": "神奈川",   # 短縮形
                "address": "横浜市西区2-2",
                "phone": None,
            },
        )
        db.flush()
        check("save", "短縮形「神奈川」→保存値", shop_b.prefecture, "神奈川県")

        # (b) 短縮形「京都」→ 京都府 で保存されることを検証（理想値で突合）
        shop_a = manager.get_or_create_shop(
            site_id=1,
            site_shop_id="__test__shop_kyoto",
            data={
                "name": "__テスト__京都店",
                "prefecture": "京都",   # 短縮形
                "address": "京都市中京区1-1",
                "phone": None,
            },
        )
        db.flush()
        check("save", "短縮形「京都」→保存値", shop_a.prefecture, "京都府")

        # (c) 既にフルネーム「東京都」→ そのまま
        shop_c = manager.get_or_create_shop(
            site_id=1,
            site_shop_id="__test__shop_tokyo",
            data={
                "name": "__テスト__東京店",
                "prefecture": "東京都",
                "address": "新宿区3-3",
                "phone": None,
            },
        )
        db.flush()
        check("save", "フルネーム「東京都」→保存値", shop_c.prefecture, "東京都")

        # (d) 名寄せ: 別サイトから同一店（同名・同県）を再投入 → 新規作成されず同一IDになること
        shop_b2 = manager.get_or_create_shop(
            site_id=2,                       # 別サイト
            site_shop_id="__test__other_site",
            data={
                "name": "__テスト__神奈川店",
                "prefecture": "神奈川",      # 再び短縮形
                "address": "横浜市西区2-2",
                "phone": None,
            },
        )
        db.flush()
        check("dedup", "名寄せ: 短縮形再投入でも同一店", shop_b2.id, shop_b.id)

    finally:
        # 何があっても必ずロールバック。実データへの影響をゼロにする。
        db.rollback()
        db.close()
        print("  → rollback 完了（テストデータは破棄。実DBは無変更）")


# ==========================================
# 3. fix_shops.py の正規化（住所→抽出→正規化）関数レベル検証
# ==========================================
def extract_and_normalize_like_fix_shops(address):
    """goobike/bds の fix_shops.py 内のロジックと同一の処理を再現。"""
    prefecture = ""
    if address:
        m = re.match(r'(東京都|北海道|大阪府|京都府|.{2,3}県)', address)
        if m:
            prefecture = m.group(1)
    # 実装と同じく抽出後に正規化
    prefecture = normalize_prefecture(prefecture)
    return prefecture


def test_fix_shops_logic():
    print("\n=== 3. fix_shops.py 正規化ロジック検証（住所→抽出→正規化） ===")
    cases = [
        ("東京都新宿区西新宿1-1-1", "東京都"),
        ("北海道札幌市中央区2-2", "北海道"),
        ("大阪府大阪市北区3-3", "大阪府"),
        ("京都府京都市中京区4-4", "京都府"),
        ("神奈川県横浜市西区5-5", "神奈川県"),
        ("愛知県名古屋市中区6-6", "愛知県"),
        ("沖縄県那覇市7-7", "沖縄県"),
        ("住所不明のテキスト", ""),     # 抽出できない→空
        ("", ""),                       # 空住所
        (None, ""),                     # None住所
    ]
    for address, expected in cases:
        check("fix_shops", f"住所{address!r}", extract_and_normalize_like_fix_shops(address), expected)


if __name__ == "__main__":
    test_normalize_function()
    test_get_or_create_shop()
    test_fix_shops_logic()

    print("\n" + "=" * 60)
    if not failures:
        print("\033[92m全テスト合格 ✓\033[0m")
        sys.exit(0)

    print(f"\033[91m{len(failures)}件 失敗\033[0m")
    for section, label, actual, expected in failures:
        print(f"  - [{section}] {label}: 結果={actual!r} ≠ 期待={expected!r}")

    # 京都関連の失敗があれば根本原因を診断表示
    kyoto_fail = [f for f in failures if "京都" in f[1]]
    if kyoto_fail:
        print("\n\033[93m▼ 発見した問題: normalize_prefecture() が「京都」を正規化できない\033[0m")
        print("  原因: 関数冒頭で pref.endswith(('都','道','府','県')) を先に判定しているため、")
        print("        '京都' は末尾が '都' に一致して mapping 適用前に素通りで返却される。")
        print("        → mapping 内の '京都':'京都府' は到達不能（デッドコード）。")
        print("  影響: 短縮形『京都』で渡された店舗は『京都府』に統一されず『京都』のまま保存される。")
        print("        ('東京'→'東京都', '大阪'→'大阪府' は末尾が京/阪なので正常に動作)")
        print("  注記: 今回はロジック非変更の方針のため未修正。修正可否は要判断。")
    sys.exit(1)
