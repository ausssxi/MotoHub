"""
取得元サイトに存在する「テスト用店舗」を取り込まないための除外定義。

取得元サイト側の運用データにテスト店が混ざっており、2026-08-09 のクロールで4件が
shops へ取り込まれた（本番で削除済み）。うち1件は prefecture=東京都 / city=熊毛郡屋久島町
という矛盾した住所を持ち、ジオコーディングの警告を毎日発生させていた。
削除しただけでは次のクロールで復活するため、取り込み時に弾く。

置き場所について:
  店舗の取り込みは goobike / bds / webike の3つの shop_collector が
  common/shop_manager.py の ShopManager を経由して行う唯一の入口を共有している。
  除外知識はそのどれか1サイトのものではなく3サイト共通の運用知識なので、
  shop_manager.py の隣（common/）に置く。scrapy にも DB にも依存しない素の定数と
  関数だけにしてあるので、単体で import して確認・テストできる。

このモジュールは判定するだけで、ログ出力も件数集計も行わない（呼び出し側の責務）。
"""

import re

# 取得元サイトの識別子による除外。
#   キー   : (サイト名, 取得元サイトの店舗識別子)
#            サイト名は各スパイダーの site_name（"GooBike" / "BDS" / "Webike"）。
#            sites.id は環境によって変わり得るため、数値IDではなく名前で持つ。
#   値     : 実測した店舗名（記録用。判定には使わない）
#
# 初期値は 2026-08-09 のクロールで取り込まれ 2026-08-11 に削除した4件。
#   shops.id 12527 / 12530 / 12540 / 12541
EXCLUDED_SHOP_IDENTIFIERS = {
    ("BDS", "164"): "TESTショップ23",
    ("BDS", "9000001"): "TEST支店",
    ("Webike", "8347"): "Webike システム開発テスト店",
    ("Webike", "16820"): "Webike VN テスト店",
}

# 店名による補助的な除外。識別子リストに未登録の新しいテスト店を拾うための保険。
# 実在店舗を巻き込まないよう、限定的なパターンだけに留めること（安易に増やさない）。
#   1) 「テスト店」「テストショップ」を含む
#   2) 先頭が TEST（大文字小文字は区別しない）。ただし直後に英字が続くものは
#      "Testarossa" のような実在しうる店名なので対象外にする。
_EXCLUDED_NAME_PATTERNS = (
    ("テスト店", re.compile(r"テスト店")),
    ("テストショップ", re.compile(r"テストショップ")),
    ("先頭がTEST", re.compile(r"^\s*TEST(?![A-Za-z])", re.IGNORECASE)),
)


def find_exclusion(site_name, identifier, shop_name):
    """
    テスト店として除外すべきかを判定する。

    除外する場合は判定内容を表す dict を返し、除外しない場合は None を返す。
        {'rule': 'identifier'|'name', 'detail': '<理由の説明>'}
    """
    key = (str(site_name or "").strip(), str(identifier or "").strip())
    if key in EXCLUDED_SHOP_IDENTIFIERS:
        return {
            "rule": "identifier",
            "detail": "除外リスト登録済み（{}）".format(EXCLUDED_SHOP_IDENTIFIERS[key]),
        }

    name = str(shop_name or "")
    for label, pattern in _EXCLUDED_NAME_PATTERNS:
        if pattern.search(name):
            return {
                "rule": "name",
                "detail": "店名パターン一致（{}）: 除外リストへの追記候補".format(label),
            }

    return None
