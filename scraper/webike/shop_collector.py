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
from utils import normalize_prefecture

class WebikeShopSpider(BaseBikeSpider):
    """
    Webikeの店舗情報を収集するスパイダー。
    画像処理は MotoHubImagePipeline に委譲し、中断時の高速停止に対応しています。
    """
    name = "webike_shop_collector"
    site_name = "Webike"
    allowed_domains = ["moto.webike.net"]
    start_urls = ["https://moto.webike.net/shop-navi/"]

    # ✨ 修正：パイプラインの設定を追加
    custom_settings = {
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 1.0,
        'COOKIES_ENABLED': False,
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, reply Gecko) Chrome/121.0.0.0 Safari/537.36',
        'ITEM_PIPELINES': {
            'common.pipelines.MotoHubImagePipeline': 300,
        },
    }

    def parse(self, response):
        """トップページのマップから各都道府県のリンクを取得"""
        # 💡 中断信号を受けている場合は停止
        if not self.crawler.engine.running:
            return

        pref_links = response.css('ul.map_todouhuken li a')
        for link in pref_links:
            # 💡 ループ内でも停止状態をチェック
            if not self.crawler.engine.running:
                break

            url = response.urljoin(link.attrib['href'])
            pref_name = link.css('::text').get().strip()
            
            # 都道府県名を正規化 (例: "東京" -> "東京都")
            pref_name = normalize_prefecture(pref_name)
            
            yield scrapy.Request(
                url, 
                callback=self.parse_shop_list, 
                meta={'prefecture': pref_name}
            )

    def parse_shop_list(self, response):
        """店舗一覧ページから店舗情報を抽出"""
        # 💡 中断信号を受けている場合は解析を停止
        if not self.crawler.engine.running:
            return

        pref_name = response.meta['prefecture']
        
        # 読み込み中のプレースホルダーを除外してカードを取得
        shop_units = response.css('div.shop-card.size-lg.v2:not(.wait-loading)')

        for unit in shop_units:
            # 💡 ループ内でもエンジン停止状態をチェック
            if not self.crawler.engine.running:
                break

            # 1. 基本情報の抽出
            name_raw = unit.css('h5.shop-title::text').get()
            if not name_raw:
                continue
            name = name_raw.strip()

            # サイト固有の店舗ID (data-shop属性)
            identifier = unit.attrib.get('data-shop')
            href = unit.css('a.title::attr(href)').get()
            
            # 住所・電話番号 (アイコン等を除去したテキストを取得)
            address = "".join(unit.css('p.shop-address::text').getall()).strip()
            phone = "".join(unit.css('p.shop-phone::text').getall()).strip()

            # 営業時間と定休日の解析
            hours = None
            holiday = None
            working_times = unit.css('p.shop-working-time')
            
            for wt in working_times:
                label = wt.css('span.pitin-title::text').get()
                full_text = wt.xpath('string(.)').get() or ""
                clean_text = full_text.replace(label or "", "").strip()
                
                if label:
                    if "営業時間" in label:
                        hours = clean_text
                    elif "定休日" in label:
                        holiday = clean_text

            # 画像URL
            img_url = unit.css('.shop-thumbnail img::attr(data-src)').get() or unit.css('.shop-thumbnail img::attr(src)').get()

            # ShopManagerに渡すデータ構造の構築
            data = {
                'name': name,
                'prefecture': pref_name,
                'address': address,
                'phone': phone,
                'website_url': response.urljoin(href) if href else None,
                'business_hours': hours,
                'regular_holiday': holiday,
                'image_url': response.urljoin(img_url) if img_url else None
            }

            # --- ✨ 共通 ShopManager を呼び出して保存・名寄せを実行 ---
            if identifier and name:
                try:
                    # ShopManagerから保存済み（または作成した）Shopオブジェクトを取得
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
                    self.logger.error(f"Failed to process Webike shop {name}: {e}")

        # ページネーション (現在のページ番号 li.current の次の li a を取得)
        next_page = response.css('ul.pagination li.current + li a.paging::attr(href)').get()
        if next_page and self.crawler.engine.running:
            yield response.follow(
                next_page, 
                callback=self.parse_shop_list, 
                meta={'prefecture': pref_name}
            )

    def closed(self, reason):
        """
        終了処理。Ctrl+C (shutdown) 時は速やかに終了します。
        """
        if reason != 'finished':
            self.logger.info(f"Spider stopped by user ({reason}).")
        else:
            self.logger.info("Webike Shop collection completed successfully.")
        super().closed(reason)

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(WebikeShopSpider)
    process.start()