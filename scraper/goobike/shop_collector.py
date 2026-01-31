import sys
import os

# ==========================================
# 0. インポートパスの解決（相対インポートエラー対策）
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if parent_dir not in sys.path:
    sys.path.append(parent_dir)

import scrapy
from scrapy.crawler import CrawlerProcess
import re
import logging

# 共通基盤のインポート
from common.base_spider import BaseBikeSpider
# ★追加: ShopManagerをインポート
from common.shop_manager import ShopManager
from utils import normalize_prefecture

class GoobikeShopSpider(BaseBikeSpider):
    """
    GooBikeの店舗情報を収集するスパイダー。
    画像処理は MotoHubImagePipeline に委譲し、中断時の高速停止に対応しています。
    """
    name = "goobike_shop_collector"
    site_name = "GooBike"
    allowed_domains = ["www.goobike.com"]
    start_urls = ["https://www.goobike.com/shop/"]

    # ✨ 修正：パイプラインの設定を追加
    custom_settings = {
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 0.8,
        'COOKIES_ENABLED': False,
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'ITEM_PIPELINES': {
            'common.pipelines.MotoHubImagePipeline': 300,
        },
    }

     # ★追加: コンストラクタでShopManagerを初期化
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.shop_manager = ShopManager(self.db)

    def parse(self, response):
        """トップページのマップから各都道府県のリンクを取得"""
        # 💡 中断信号を受けている場合は停止
        if not self.crawler.engine.running:
            return

        pref_links = response.css(".mapBox li a")
        for link in pref_links:
            if not self.crawler.engine.running: break

            url = response.urljoin(link.attrib['href'])
            
            # 都道府県名の取得と正規化 (例: "東京(123)" -> "東京都")
            raw_pref = link.xpath("text()").get() or ""
            pref_name = re.sub(r'[\(\uff08].*?[\)\uff09]', '', raw_pref).strip()
            pref_name = normalize_prefecture(pref_name)
            
            yield scrapy.Request(
                url, 
                callback=self.parse_shop_list, 
                meta={'prefecture': pref_name}
            )

    def parse_shop_list(self, response):
        """店舗一覧ページから各店舗の情報を抽出"""
        # 💡 中断信号を受けている場合は停止
        if not self.crawler.engine.running:
            return

        pref_name = response.meta['prefecture']
        
        for unit in response.css("div.shop"):
            # 💡 ループ内でも停止状態をチェック
            if not self.crawler.engine.running:
                break

            name_link = unit.css(".shop_name a")
            if not name_link:
                continue
            
            name = name_link.xpath("string(.)").get().strip()
            href = name_link.attrib.get('href', '')
            
            # サイト固有の店舗ID (client_XXXXXX) の抽出
            identifier = None
            id_match = re.search(r'client_(\d+)', href)
            if id_match:
                identifier = id_match.group(1)

            # 評価点の解析
            rating_text = "".join(unit.css(".review_point *::text").getall())
            rating_match = re.search(r'(\d+\.\d+)', rating_text)
            rating = float(rating_match.group(1)) if rating_match else 0.0

            # 基本データの構築
            data = {
                'name': name,
                'prefecture': pref_name,
                'address': (unit.css(".shop_address::text").get() or "").strip(),
                'website_url': response.urljoin(href) if href else None,
                'rating': rating,
                'business_hours': (unit.css(".shop_time::text").get() or "").strip(),
                'regular_holiday': (unit.css(".shop_holiday::text").get() or "").strip(),
                'image_url': unit.css(".shop_img img::attr(data-original)").get() or unit.css(".shop_img img::attr(src)").get(),
                'phone': None 
            }

            # --- ✨ 共通 ShopManager を呼び出して保存・名寄せを実行 ---
            if identifier and name:
                try:
                    # get_or_create_shop は保存された Shop オブジェクトを返す
                    shop = self.shop_manager.get_or_create_shop(self.site_id, identifier, data)
                    self.db.commit()

                    # ✨ 修正：共通パイプラインにアイテムを渡す（店舗画像保存用）
                    if shop and data.get('image_url'):
                        yield {
                            'target_type': 'shop',
                            'id': shop.id,
                            'image_urls': [response.urljoin(data['image_url'])]
                        }

                except Exception as e:
                    self.db.rollback()
                    self.logger.error(f"Failed to process shop {name}: {e}")

        # ページネーションの処理
        next_page = response.css(".pager_next a::attr(href)").get()
        if next_page and self.crawler.engine.running:
            yield response.follow(
                next_page, 
                callback=self.parse_shop_list, 
                meta={'prefecture': pref_name}
            )

    def closed(self, reason):
        """
        終了処理。Ctrl+C (shutdown) 時はログを出力して速やかに終了します。
        """
        if reason != 'finished':
            self.logger.info(f"Spider stopped by user ({reason}).")
        else:
            self.logger.info("Shop collection completed successfully.")
        super().closed(reason)

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(GoobikeShopSpider)
    process.start()