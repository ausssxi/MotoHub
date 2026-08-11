import sys
import os

# ==========================================
# 0. インポートパスの解決
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
from common.base_spider import BaseBikeSpider, MOTOHUB_USER_AGENT
# ★追加: ShopManagerをインポート
from common.shop_manager import ShopManager
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

    custom_settings = {
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 1.0,
        'COOKIES_ENABLED': False,
        # Typo修正: reply Gecko -> like Gecko
        'USER_AGENT': MOTOHUB_USER_AGENT,
        'ROBOTSTXT_OBEY': True,
        'ITEM_PIPELINES': {
            'common.pipelines.MotoHubImagePipeline': 300,
        },
    }

    # ★追加: コンストラクタでShopManagerを初期化
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        # site_name はテスト店の除外判定（common/shop_exclusions.py）のキーに使う
        self.shop_manager = ShopManager(self.db, site_name=self.site_name)

    def parse(self, response):
        """トップページのマップから各都道府県のリンクを取得"""
        if not self.crawler.engine.running:
            return

        pref_links = response.css('ul.map_todouhuken li a')
        for link in pref_links:
            if not self.crawler.engine.running: break

            url = response.urljoin(link.attrib['href'])
            pref_name = link.css('::text').get().strip()
            
            # 都道府県名を正規化
            pref_name = normalize_prefecture(pref_name)
            
            yield scrapy.Request(
                url, 
                callback=self.parse_shop_list, 
                meta={'prefecture': pref_name}
            )

    def parse_shop_list(self, response):
        """店舗一覧ページから店舗情報を抽出"""
        if not self.crawler.engine.running:
            return

        pref_name = response.meta['prefecture']
        
        # 読み込み中のプレースホルダーを除外してカードを取得
        shop_units = response.css('div.shop-card.size-lg.v2:not(.wait-loading)')

        for unit in shop_units:
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
            
            # 住所・電話番号
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

            # バイク店ジャンル/サービスのバッジ（Webike固有・任意）
            # 例: 公取協加盟店 / HONDA正規店 / 認証工場 / 車検受付 / 修理・点検整備
            service_tags = [
                t.strip() for t in unit.css('ul.tag-service li span.b-outline-gray::text').getall()
                if t.strip()
            ]

            # 基本データの構築
            data = {
                'name': name,
                'prefecture': pref_name,
                'address': address,
                'phone': phone,
                'website_url': response.urljoin(href) if href else None,
                'business_hours': hours,
                'regular_holiday': holiday,
                'image_url': response.urljoin(img_url) if img_url else None,
                'service_tags': service_tags or None,
            }

            # --- 共通 ShopManager を呼び出して保存・名寄せを実行 ---
            if identifier and name:
                try:
                    # ShopManagerから保存済み（または作成した）Shopオブジェクトを取得
                    shop = self.shop_manager.get_or_create_shop(self.site_id, identifier, data)
                    self.db.commit()

                    # 共通パイプラインにアイテムを渡す（店舗画像保存用）
                    if shop and data.get('image_url'):
                        yield {
                            'target_type': 'shop',
                            'id': shop.id,
                            'image_urls': [data['image_url']]
                        }

                except Exception as e:
                    self.db.rollback()
                    self.logger.error(f"Failed to process Webike shop {name}: {e}")

        # ページネーション
        next_page = response.css('ul.pagination li.current + li a.paging::attr(href)').get()
        if next_page and self.crawler.engine.running:
            yield response.follow(
                next_page, 
                callback=self.parse_shop_list, 
                meta={'prefecture': pref_name}
            )

    def closed(self, reason):
        if reason != 'finished':
            self.logger.info(f"Spider stopped by user ({reason}).")
        else:
            self.logger.info("Webike Shop collection completed successfully.")
        super().closed(reason)

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(WebikeShopSpider)
    process.start()