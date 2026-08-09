"""
スクレイパー共通の User-Agent 定義と、画像取得可否の判定。

このモジュールは scrapy / sqlalchemy 等に依存しない軽量モジュールにしてある。
scrapy を使わないスクリプト（画像同期・spec 収集など）が、重い依存を読み込まずに
UA 定義と取得可否判定だけを import できるようにするため、common/base_spider.py から
分離している（base_spider は後方互換のためここから再エクスポートする）。
"""

from urllib.parse import urlparse

# 全スパイダー・全取得処理で共通の User-Agent。
# 実在ブラウザ（Chrome等）を騙るのをやめ、MotoHubBot として正直に名乗る。
# 理由: クロール元を明示して robots.txt に従い行儀よく巡回するため。連絡先URLを
# 含めることで、取得先サイトの運営が問題発生時に MotoHub 側へ連絡できるようにする。
MOTOHUB_USER_AGENT = "MotoHubBot/1.0 (+https://motohub.jp/)"


# robots.txt により画像取得が拒否されているホスト。ここに含まれるホストからは
# 新規ダウンロードを行わない（既存の取得済み画像は削除しない・配信は継続する）。
#
# img.webike-cdn.net:
#   robots.txt（確認日 2026-08-09）が
#       User-agent: *
#       Disallow: /
#   となっており、Googlebot と Twitterbot のみ例外的に許可されている。
#   MotoHubBot は許可対象外のため、当ホストからの新規取得を停止する。
#   （goobike の picture.goobike.com は robots.txt が404＝制限なし、
#     BDS の images/cdn.bds-bikesensor.net は403＝制限なしのため対象外。）
DISALLOWED_IMAGE_HOSTS = {"img.webike-cdn.net"}


def is_image_url_allowed(url):
    """
    画像URLが取得可能かを判定する。
    ホストが DISALLOWED_IMAGE_HOSTS に含まれていれば False（取得禁止）を返す。
    それ以外・判定不能な場合は True を返す。
    """
    if not url:
        return True

    host = (urlparse(str(url)).hostname or "").lower()

    return host not in DISALLOWED_IMAGE_HOSTS
