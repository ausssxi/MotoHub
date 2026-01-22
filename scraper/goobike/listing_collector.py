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
import datetime
import logging

# 共通基盤のインポート
from common.database import Listing, BikeModelIdentifier, ShopIdentifier
from common.base_spider import BaseBikeSpider

class GooBikeListingSpider(BaseBikeSpider):
    """
    GooBikeの出品情報を収集するスパイダー。
    保存・更新・完売判定は BaseBikeSpider の共通メソッドを使用します。
    """
    name = "goobike_listings"
    site_name = "GooBike"
    allowed_domains = ["www.goobike.com"]
    start_urls = ["https://www.goobike.com/maker-top/index.html"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 16,
        'DOWNLOAD_DELAY': 0.3,
        'RANDOMIZE_DOWNLOAD_DELAY': True,
        'COOKIES_ENABLED': True,
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    }

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        
        self.logger.info("Initializing GooBike caches...")
        # モデル・ショップの識別子キャッシュ構築
        self.model_ident_cache = {
            ident: b_id for ident, b_id in self.db.query(
                BikeModelIdentifier.identifier, BikeModelIdentifier.bike_model_id
            ).filter(BikeModelIdentifier.site_id == self.site_id).all()
        }
        self.shop_cache = {
            ident: s_id for ident, s_id in self.db.query(
                ShopIdentifier.identifier, ShopIdentifier.shop_id
            ).filter(ShopIdentifier.site_id == self.site_id).all()
        }
        
        # 完売判定用に、現在の「出品中」URLリストを取得
        self.known_urls = {
            l.source_url for l in self.db.query(Listing.source_url).filter(
                Listing.site_id == self.site_id, Listing.is_sold_out == False
            ).all()
        }

    def parse(self, response):
        maker_links = response.css(".makerlist .mj a::attr(href)").getall()
        for href in maker_links:
            yield response.follow(href, callback=self.parse_models)

    def parse_models(self, response):
        bike_list_items = response.css("li.bike_list")
        for item in bike_list_items:
            identifier = item.css("input[name='model']::attr(value)").get()
            model_path = item.css("a::attr(href)").get()
            if identifier and model_path:
                bike_model_id = self.model_ident_cache.get(identifier)
                if bike_model_id:
                    yield response.follow(
                        model_path, 
                        callback=self.parse_listings, 
                        meta={'bike_model_id': bike_model_id}
                    )

    def parse_listings(self, response):
        bike_model_id = response.meta['bike_model_id']
        vehicle_elements = response.css(".bike_sec")
        
        if not vehicle_elements: return

        for v_el in vehicle_elements:
            v_link_el = v_el.css("h4 span a")
            if not v_link_el: continue
            
            v_url = response.urljoin(v_link_el.attrib.get('href'))
            self.found_urls.add(v_url)

            try:
                listing_data = self.extract_info(v_el, bike_model_id, response, v_url)
                if not listing_data: continue

                # 共通メソッドを使用して保存・更新
                if v_url in self.known_urls:
                    self.update_listing(v_url, listing_data)
                else:
                    # 他サイト重複チェックも共通メソッドで
                    if not self.is_cross_site_duplicate(listing_data):
                        self.save_listing(listing_data)
            except Exception as e:
                self.logger.error(f"Error at {v_url}: {e}")

        # ページ単位でコミット
        self.db.commit()

        next_page = response.css("li.next a::attr(href)").get()
        if next_page:
            yield response.follow(next_page, callback=self.parse_listings, meta=response.meta)

    def extract_info(self, v_el, bike_model_id, response, v_url):
        """GooBike特有のHTML解析ロジック（抽出のみ担当）"""
        try:
            price_txt = "".join(v_el.css("td.num_td *::text").getall()).replace(',', '')
            total_txt = "".join(v_el.css("span.total *::text, .price_total *::text").getall()).replace(',', '')
            p_match = re.search(r'(\d+\.?\d*)', price_txt)
            price_val = int(float(p_match.group(1)) * 10000) if p_match else 0
            t_match = re.search(r'(\d+\.?\d*)', total_txt)
            total_val = int(float(t_match.group(1)) * 10000) if t_match else None

            year, mile, has_repair = None, None, False
            for li in v_el.css(".cont01 ul li"):
                text = li.xpath("string(.)").get() or ""
                if "年式" in text:
                    y_m = re.search(r'(\d{4})', text)
                    if y_m: year = int(y_m.group(1))
                elif "走行" in text:
                    m_m = re.search(r'(\d+)', text.replace(',', ''))
                    if m_m: mile = int(m_m.group(1))
                elif "修復" in text:
                    has_repair = "あり" in text

            shop_site_id = None
            shop_href = v_el.css(".shop_name a::attr(href)").get()
            if shop_href:
                s_match = re.search(r'client_(\d+)', shop_href)
                if s_match: shop_site_id = s_match.group(1)

            img_el = v_el.css(".bike_img img")
            img_url = img_el.attrib.get('real-url') or img_el.attrib.get('src')
            image_urls = [response.urljoin(img_url)] if img_url and 'loading' not in img_url.lower() else []

            return {
                'bike_model_id': bike_model_id,
                'shop_id': self.shop_cache.get(shop_site_id),
                'title': v_el.css("h4 span a::text").get(default="").strip(),
                'source_url': v_url,
                'price': price_val,
                'total_price': total_val,
                'model_year': year,
                'mileage': mile,
                'has_repair_history': has_repair,
                'image_urls': image_urls,
            }
        except Exception: return None

    def closed(self, reason):
        """完売処理を共通メソッドで実行"""
        self.handle_sold_out(self.known_urls)
        super().closed(reason)

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(GooBikeListingSpider)
    process.start()