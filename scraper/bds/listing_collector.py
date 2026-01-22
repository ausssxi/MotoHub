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
import datetime
import logging

# 共通基盤のインポート
from common.database import Listing, BikeModelIdentifier, ShopIdentifier
from common.base_spider import BaseBikeSpider

class BDSListingSpider(BaseBikeSpider):
    """
    BDSバイクセンサーの出品情報を収集するスパイダー。
    Ctrl+C で即座に停止し、重い処理をスキップするように最適化されています。
    """
    name = "bds_listings"
    site_name = "BDS"
    allowed_domains = ["www.bds-bikesensor.net"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 0.7,
        'COOKIES_ENABLED': False,
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'ITEM_PIPELINES': {
            'common.pipelines.MotoHubImagePipeline': 300,
        },
    }

    MAKER_LIST = ["honda", "suzuki", "yamaha", "kawasaki", "bmw", "ktm", "ducati", "harley_davidson", "triumph"]

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        
        self.logger.info("Initializing BDS caches...")
        self.model_ident_cache = {
            i.identifier: i.bike_model_id for i in self.db.query(
                BikeModelIdentifier.identifier, BikeModelIdentifier.bike_model_id
            ).filter(BikeModelIdentifier.site_id == self.site_id).all()
        }
        self.shop_cache = {
            i.identifier: i.shop_id for i in self.db.query(
                ShopIdentifier.identifier, ShopIdentifier.shop_id
            ).filter(ShopIdentifier.site_id == self.site_id).all()
        }
        
        self.known_urls = {
            l.source_url for l in self.db.query(Listing.source_url).filter(
                Listing.site_id == self.site_id, Listing.is_sold_out == False
            ).all()
        }

    def start_requests(self):
        base_url = "https://www.bds-bikesensor.net/bike/maker/"
        for slug in self.MAKER_LIST:
            yield scrapy.Request(url=base_url + slug, callback=self.parse_maker_page)

    def parse_maker_page(self, response):
        model_items = response.css("div.col, .model_item")
        for item in model_items:
            m_input = item.css("input.model-checkbox::attr(value)").get()
            href = item.css("a.c-bike_image::attr(href), a.c-link_block::attr(href)").get()
            if m_input and href:
                bike_model_id = self.model_ident_cache.get(m_input)
                if bike_model_id:
                    yield response.follow(
                        href, 
                        callback=self.parse_listings, 
                        meta={'bike_model_id': bike_model_id}
                    )

    def parse_listings(self, response):
        # 💡 中断信号を受けている場合は処理を行わずに終了
        if not self.crawler.engine.running:
            return

        bike_model_id = response.meta['bike_model_id']
        bike_blocks = response.css("li.type_bike, li.type_bike_sp")
        
        for bike in bike_blocks:
            # 💡 ループ内でも停止状態をチェックして即時離脱を助ける
            if not self.crawler.engine.running:
                break

            v_url = response.urljoin(bike.css(".c-search_block_title a::attr(href), .c-search_block_title02 a::attr(href)").get())
            if not v_url: continue
            self.found_urls.add(v_url)

            item_data = self.extract_bike_data(response, bike, bike_model_id, v_url)
            if not item_data: continue

            try:
                record = None
                if v_url in self.known_urls:
                    self.update_listing(v_url, item_data)
                    record = self.db.query(Listing).filter(Listing.source_url == v_url).first()
                else:
                    if not self.is_cross_site_duplicate(item_data):
                        self.save_listing(item_data)
                        self.db.flush() 
                        record = self.db.query(Listing).filter(Listing.source_url == v_url).first()

                if record and item_data.get('image_urls'):
                    yield {
                        'target_type': 'listing',
                        'id': record.id,
                        'image_urls': item_data['image_urls']
                    }
            except Exception as e:
                self.logger.error(f"Error processing {v_url}: {e}")

        # 💡 中断時はコミットをスキップまたは最小限にする
        try:
            self.db.commit()
        except Exception as e:
            self.db.rollback()
            self.logger.error(f"Commit error: {e}")

        # ページネーション
        next_page = response.css("div.c-pager a.c-btn_next::attr(href)").get()
        if next_page and self.crawler.engine.running:
            yield response.follow(next_page, callback=self.parse_listings, meta=response.meta)

    def extract_bike_data(self, response, bike, bike_model_id, v_url):
        """BDS特有のHTML解析ロジック"""
        try:
            price_val, total_price_val = 0, None
            for p_item in bike.css(".c-search_block_price"):
                l_text = p_item.css(".c-search_block_price_title::text").get()
                v_text = "".join(p_item.css(".c-search_block_price_text *::text").getall()).replace(',', '').strip()
                match = re.search(r'(\d+\.?\d*)', v_text)
                if match:
                    num = int(float(match.group(1)) * 10000)
                    if l_text and "本体価格" in l_text: price_val = num
                    elif l_text and "支払総額" in l_text: total_price_val = num

            year, mile, has_repair = None, None, False
            for col in bike.css(".c-search_status_col"):
                h_txt = col.css(".c-search_status_head::text").get() or ""
                v_txt = "".join(col.css(".c-search_status_title01 *::text").getall()).strip()
                if "モデル年" in h_txt:
                    y_m = re.search(r'(\d{4})', v_txt); year = int(y_m.group(1)) if y_m else None
                elif "距離" in h_txt:
                    m_m = re.search(r'(\d+)', v_txt.replace(',', '')); mile = int(m_m.group(1)) if m_m else None
                elif "修復歴" in h_txt: has_repair = "あり" in v_txt

            shop_href = bike.css(".c-search_block_bottom_lead a::attr(href), .c-search_block_shop_title01 a::attr(href)").get()
            shop_id = None
            if shop_href:
                id_match = re.search(r'client/(\d+)', shop_href)
                if id_match:
                    shop_id = self.shop_cache.get(id_match.group(1))

            if not shop_id: return None

            img_url = bike.css("figure.c-img_cover::attr(data-src)").get() or bike.css("figure.c-img_cover::attr(src)").get()

            return {
                'bike_model_id': bike_model_id,
                'shop_id': shop_id,
                'title': (bike.css(".c-search_block_title a::text, .c-search_block_title02 a::text").get() or "").strip(),
                'source_url': v_url,
                'price': price_val,
                'total_price': total_price_val,
                'model_year': year,
                'mileage': mile,
                'has_repair_history': has_repair,
                'image_urls': [response.urljoin(img_url)] if img_url else []
            }
        except Exception: return None

    def closed(self, reason):
        """
        終了処理。Ctrl+C (shutdown) 時は重たい完売チェックをスキップして即座に終了します。
        """
        if reason == 'finished':
            self.logger.info("Normal finish. Running sold-out cleanup...")
            self.handle_sold_out(self.known_urls)
        else:
            # reason が 'shutdown' (Ctrl+C) や 'cancelled' の場合はこちら
            self.logger.info(f"Spider closed by user ({reason}). Skipping heavy cleanup for quick exit.")
            
        super().closed(reason)

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(BDSListingSpider)
    process.start()