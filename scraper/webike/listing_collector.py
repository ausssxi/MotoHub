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

class WebikeListingSpider(BaseBikeSpider):
    """
    Webikeの出品情報を収集するスパイダー。
    GooBike/BDSと同様に、1件ごとのDB確定により画像同期の安定性を高めています。
    """
    name = "webike_listings"
    site_name = "Webike"
    allowed_domains = ["moto.webike.net"]
    start_urls = ["https://moto.webike.net/maker/"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 16,
        'DOWNLOAD_DELAY': 0.5,
        'RANDOMIZE_DOWNLOAD_DELAY': True,
        'COOKIES_ENABLED': False,
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'ITEM_PIPELINES': {
            'common.pipelines.MotoHubImagePipeline': 300,
        },
    }

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        
        self.logger.info("Initializing Webike caches...")
        
        # 車種識別子キャッシュ
        self.model_ident_cache = {
            i.identifier: i.bike_model_id for i in self.db.query(
                BikeModelIdentifier.identifier, BikeModelIdentifier.bike_model_id
            ).filter(BikeModelIdentifier.site_id == self.site_id).all()
        }
        # ショップ識別子キャッシュ（起動時に全ロード）
        self.shop_cache = {
            i.identifier: i.shop_id for i in self.db.query(
                ShopIdentifier.identifier, ShopIdentifier.shop_id
            ).filter(ShopIdentifier.site_id == self.site_id).all()
        }
        
        # 完売判定用
        self.known_urls = {
            l.source_url for l in self.db.query(Listing.source_url).filter(
                Listing.site_id == self.site_id, 
                Listing.is_sold_out == False
            ).all()
        }

    def parse(self, response):
        maker_links = response.css('div.maker ul.dotline li a::attr(href)').getall()
        for href in set(maker_links):
            yield response.follow(href, callback=self.parse_models)

    def parse_models(self, response):
        bike_items = response.css('div.motoset ul.dotline li, div#category_search_list div.moto ul li')
        
        for item in bike_items:
            href = item.css('p.model_name a::attr(href)').get() or item.css('a.img-thumbnail::attr(href)').get()
            if not href: continue

            identifier = item.css('input[name="model_code_checkList"]::attr(value)').get()
            if not identifier:
                parts = [p for p in href.split('/') if p]
                if len(parts) >= 2:
                    identifier = parts[-2] if parts[-1] == 'list' else parts[-1]

            if identifier:
                bike_model_id = self.model_ident_cache.get(identifier)
                if bike_model_id:
                    list_url = href if href.endswith('list/') else href.rstrip('/') + '/list/'
                    yield response.follow(
                        list_url, 
                        callback=self.parse_listings, 
                        meta={'bike_model_id': bike_model_id}
                    )

    def parse_listings(self, response):
        if not self.crawler.engine.running: return

        bike_model_id = response.meta['bike_model_id']
        listings = response.css('li.li_bike_list:not(.recommend-block)')
        
        for li in listings:
            if not self.crawler.engine.running: break

            v_link = li.css('a.flex::attr(href)').get()
            if not v_link: continue
            
            v_url = response.urljoin(v_link)
            self.found_urls.add(v_url)

            # 1. データ抽出
            item_data = self.extract_listing_data(response, li, bike_model_id, v_url)
            if not item_data: continue

            try:
                # 2. 💾 保存・更新
                if v_url in self.known_urls:
                    self.update_listing(v_url, item_data)
                else:
                    if not self.is_cross_site_duplicate(item_data):
                        self.save_listing(item_data)

                # ✨ descriptionの追加保存処理 (ここを追加)
                if item_data.get('description'):
                    record = self.db.query(Listing).filter(Listing.source_url == v_url).first()
                    if record:
                        record.description = item_data['description']

                # ✨【重要】Pipelineが参照できるように即座にcommit
                self.db.commit()

                # IDが確定した状態でレコードを再取得
                record = self.db.query(Listing).filter(Listing.source_url == v_url).first()

                # 3. 画像同期タスクを Pipeline へ渡す
                if record and item_data.get('image_urls'):
                    yield {
                        'target_type': 'listing',
                        'id': record.id,
                        'image_urls': item_data['image_urls']
                    }
            except Exception as e:
                self.db.rollback()
                self.logger.error(f"Error processing {v_url}: {e}")

        # ページネーション
        next_page = response.css('ul.pagination li.current + li a.paging::attr(href)').get()
        if next_page and self.crawler.engine.running:
            yield response.follow(next_page, callback=self.parse_listings, meta=response.meta)

    def extract_listing_data(self, response, li, bike_model_id, v_url):
        try:
            # 価格解析 (ASK対応)
            price_text = li.css('.prices li.small-price span::text').get() or "0"
            price_val = 0
            if "ASK" not in price_text:
                p_match = re.search(r'(\d+\.?\d*)', price_text.replace(',', ''))
                if p_match: price_val = int(float(p_match.group(1)) * 10000)

            total_text = li.css('.prices li:not(.small-price) span::text').get() or ""
            total_val = None
            if total_text and "ASK" not in total_text and "―" not in total_text:
                t_match = re.search(r'(\d+\.?\d*)', total_text.replace(',', ''))
                if t_match: total_val = int(float(t_match.group(1)) * 10000)

            # スペック解析
            mile_text = li.css('.box-distace li.border .distance span::text').get() or ""
            mile = 0
            if all(s not in mile_text for s in ["走行不明", "減算歴車", "-"]):
                m_match = re.search(r'(\d+)', mile_text.replace(',', ''))
                if m_match: mile = int(m_match.group(1))

            year_text = li.css('.box-distace li:not(.border) .distance span::text').get() or ""
            year = None
            y_match = re.search(r'(\d{4})', year_text)
            if y_match: year = int(y_match.group(1))

            # ✨ ショップIDの特定とデバッグログ
            shop_id = None
            shop_href = li.css('.shop_name a::attr(href)').get()
            if shop_href:
                s_match = re.search(r'shop/(\d+)', shop_href)
                if s_match:
                    site_shop_id = s_match.group(1)
                    shop_id = self.shop_cache.get(site_shop_id)
                    
                    # 🔍 紐付け確認用ログ
                    self.logger.info(f"DEBUG: [Webike ShopID: {site_shop_id}] -> [Internal ID: {shop_id}]")

            # ショップが見つからない場合はスキップ（デグレーション防止ガード）
            if not shop_id:
                self.logger.warning(f"Shop not found in cache for {v_url}. Listing skipped.")
                return None
            
            # ✨ 説明文の取得 (キャッチコピー + 本文)
            catch_copy = li.css('p.catch-copy::text').get() or ""
            # spanタグは他の要素の中にもあるので、li直下のspanを狙うためにxpathを使用
            comment_text = li.xpath('./span/text()').get() or ""
            
            description = f"{catch_copy}\n{comment_text}".strip()
            
            # 確認用ログ
            if description:
                self.logger.info(f"📄 Description found: {description[:20]}...")

            # 画像URL (Lazy load用のdata-srcを優先)
            main_img = li.css('.img_bike_list img::attr(data-src)').get() or li.css('.img_bike_list img::attr(src)').get()
            image_urls = [response.urljoin(main_img)] if main_img and 'loading' not in main_img.lower() else []

            return {
                'bike_model_id': bike_model_id,
                'shop_id': shop_id,
                'title': li.css('h2 strong::text').get(default="").strip(),
                'description': description, # 追加
                'source_url': v_url,
                'price': price_val,
                'total_price': total_val,
                'model_year': year,
                'mileage': mile,
                'image_urls': image_urls
            }
        except Exception as e:
            self.logger.error(f"Extraction failed: {e}")
            return None

    def closed(self, reason):
        if reason == 'finished':
            self.handle_sold_out()
        super().closed(reason)

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(WebikeListingSpider)
    process.start()