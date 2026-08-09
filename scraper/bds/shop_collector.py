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

class BdsShopSpider(BaseBikeSpider):
    """
    BDSバイクセンサーの店舗情報を収集するスパイダー。
    画像処理は MotoHubImagePipeline に委譲し、中断時の高速停止に対応しています。
    """
    name = "bds_shop_collector"
    site_name = "BDS"
    allowed_domains = ["www.bds-bikesensor.net"]

    # ✨ 修正：パイプラインの設定を追加
    custom_settings = {
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 1.0,
        'COOKIES_ENABLED': False,
        'USER_AGENT': MOTOHUB_USER_AGENT,
        'ROBOTSTXT_OBEY': True,
        'ITEM_PIPELINES': {
            'common.pipelines.MotoHubImagePipeline': 300,
        },
    }

    # 都道府県コードの定義
    PREF_MAP = {
        "01": "北海道", "02": "青森県", "03": "岩手県", "04": "宮城県", "05": "秋田県", "06": "山形県", "07": "福島県",
        "08": "茨城県", "09": "栃木県", "10": "群馬県", "11": "埼玉県", "12": "千葉県", "13": "東京都", "14": "神奈川県",
        "15": "新潟県", "16": "富山県", "17": "石川県", "18": "福井県", "19": "山梨県", "20": "長野県", "21": "岐阜県",
        "22": "静岡県", "23": "愛知県", "24": "三重県", "25": "滋賀県", "26": "京都府", "27": "大阪府", "28": "兵庫県",
        "29": "奈良県", "30": "和歌山県", "31": "鳥取県", "32": "島根県", "33": "岡山県", "34": "広島県", "35": "山口県",
        "36": "徳島県", "37": "香川県", "38": "愛媛県", "39": "高知県", "40": "福岡県", "41": "佐賀県", "42": "長崎県",
        "43": "熊本県", "44": "大分県", "45": "宮崎県", "46": "鹿児島県", "47": "沖縄県"
    }

    # ★追加: コンストラクタでShopManagerを初期化
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.shop_manager = ShopManager(self.db)

    def start_requests(self):
        """都道府県ごとにリクエストを開始"""
        base_url = "https://www.bds-bikesensor.net/shop?prefectureCodes%5B%5D="
        for code, name in self.PREF_MAP.items():
            # 💡 中断信号を受けている場合はリクエスト生成を停止
            if not self.crawler.engine.running:
                return

            # 都道府県名の正規化 (例: "東京" -> "東京都")
            pref_name = normalize_prefecture(name)
            yield scrapy.Request(
                base_url + code, 
                callback=self.parse_shop_list, 
                meta={'prefecture': pref_name}
            )

    def parse_shop_list(self, response):
        """店舗一覧ページから店舗情報を抽出"""
        # 💡 中断信号を受けている場合は解析を停止
        if not self.crawler.engine.running:
            return

        pref_name = response.meta['prefecture']
        shop_units = response.css("li.c-search_block_list_item.type_shop")

        for unit in shop_units:
            # 💡 ループ内でもエンジン停止状態をチェック
            if not self.crawler.engine.running:
                break

            name_link = unit.css(".c-search_block_shop_title01 a")
            if not name_link:
                continue
            
            name = name_link.xpath("string(.)").get().strip()
            href = name_link.attrib.get('href', '')
            
            # サイト固有の店舗ID (client/XXXXXX) の抽出
            identifier = None
            id_match = re.search(r'client/(\d+)', href)
            if id_match:
                identifier = id_match.group(1)

            # 詳細情報のテーブル解析
            address, hours, holiday, phone = "", "", "", ""
            rows = unit.css(".c-search_block_shop-info_table table tr")
            for row in rows:
                th_txt = row.xpath("string(th)").get() or ""
                td_txt = row.xpath("string(td)").get() or ""
                
                if "住所" in th_txt: address = td_txt.strip()
                elif "営業時間" in th_txt: hours = td_txt.strip()
                elif "定休日" in th_txt: holiday = td_txt.strip()
                elif "電話番号" in th_txt: phone = td_txt.strip()

            img_url = unit.css("figure.c-delay_load::attr(data-src)").get() or unit.css("figure.c-delay_load img::attr(src)").get()

            # 基本データの構築
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
                    # ShopManagerから保存済みのShopオブジェクトを取得
                    shop = self.shop_manager.get_or_create_shop(self.site_id, identifier, data)
                    self.db.commit()

                    # ✨ 修正：共通パイプラインにアイテムを渡す（店舗画像保存用）
                    if shop and data.get('image_url'):
                        yield {
                            'target_type': 'shop',
                            'id': shop.id,
                            'image_urls': [data['image_url']]
                        }

                except Exception as e:
                    self.db.rollback()
                    self.logger.error(f"Failed to process BDS shop {name}: {e}")

        # ページネーションの処理
        next_page = response.css("div.c-pager a.c-btn_next::attr(href)").get()
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
            self.logger.info("BDS Shop collection completed successfully.")
        super().closed(reason)

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(BdsShopSpider)
    process.start()