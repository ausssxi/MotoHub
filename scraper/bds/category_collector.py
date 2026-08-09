import scrapy
from scrapy.crawler import CrawlerProcess
import os
import re
import sys
import datetime
import logging
import requests
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, update
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# ==========================================
# 0. パス調整 & 共通ユーティリティ読込
# ==========================================
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
sys.path.append(parent_dir)

from utils import normalize_name, extract_displacement
from common.base_spider import MOTOHUB_USER_AGENT

# ==========================================
# 1. データベース設定
# ==========================================
env_path = os.path.join(parent_dir, '..', '.env')
load_dotenv(dotenv_path=env_path)

def get_env_or_exit(key, default=None):
    val = os.getenv(key, default)
    if val is None:
        logging.error(f"致命的エラー: {key} が設定されていません。")
        sys.exit(1)
    return val

DATABASE_URL = f"mysql+pymysql://{get_env_or_exit('DB_USERNAME')}:{get_env_or_exit('DB_PASSWORD')}@{get_env_or_exit('DB_HOST', 'db')}:{get_env_or_exit('DB_PORT', '3306')}/{get_env_or_exit('DB_DATABASE')}"

engine = create_engine(DATABASE_URL, pool_pre_ping=True)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

class Base(DeclarativeBase): pass

class Category(Base):
    __tablename__ = "categories"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(255), unique=True)
    icon_url = Column(String(255)) 
    local_icon_path = Column(String(255))

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(255), nullable=False)
    category_id = Column(BigInteger, nullable=True)
    displacement = Column(Integer, nullable=True)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

# ==========================================
# 2. Pipeline
# ==========================================
class CategoryPipeline:
    # 最小限のマッピングのみ適用
    CATEGORY_MAPPING = {
        "電動バイク(EV)": "EV",
        "電動バイク": "EV",
    }

    def open_spider(self, spider):
        self.session = SessionLocal()
        
        # カテゴリIDキャッシュ（正規化名もキーに追加してヒット率を上げる）
        categories = self.session.query(Category).all()
        self.cat_map = {}
        for c in categories:
            self.cat_map[c.name] = c.id
            # 正規化した名前でも引けるようにする
            self.cat_map[self.normalize(c.name)] = c.id
        
        spider.logger.info(f"カテゴリ定義ロード: {len(categories)}件")

        # 車種キャッシュ
        all_models = self.session.query(BikeModel).all()
        self.model_cache = {}
        for m in all_models:
            norm = normalize_name(m.name)
            if norm not in self.model_cache: self.model_cache[norm] = []
            self.model_cache[norm].append(m.id)

        # 保存先パス決定
        project_root = os.path.dirname(parent_dir)
        candidates = [
            "/var/www/storage/app/public",
            os.path.join(project_root, "backend", "storage", "app", "public"),
            os.path.join(project_root, "storage", "app", "public"),
        ]
        self.storage_base = None
        for p in candidates:
            if os.path.exists(p):
                self.storage_base = p
                break
        if not self.storage_base:
            self.storage_base = "/var/www/storage/app/public"
            try: os.makedirs(self.storage_base, exist_ok=True)
            except: pass

    def normalize(self, name):
        """照合用の正規化（空白削除、小文字化）"""
        return re.sub(r'\s+', '', str(name).lower())

    def process_item(self, item, spider):
        # A. カテゴリ画像の更新
        if item.get('type') == 'category_image':
            raw_name = item['category_name']
            img_url = item['image_url']
            
            # 1. マッピング確認
            mapped_name = self.CATEGORY_MAPPING.get(raw_name, raw_name)
            
            # 2. 完全一致または正規化一致でIDを探す
            cat_id = self.cat_map.get(mapped_name)
            if not cat_id:
                cat_id = self.cat_map.get(self.normalize(mapped_name))

            if cat_id and img_url:
                local_path = self.download_image(img_url, cat_id, spider)
                try:
                    update_values = {"icon_url": img_url}
                    if local_path:
                        update_values["local_icon_path"] = local_path
                    self.session.execute(
                        update(Category).where(Category.id == cat_id).values(**update_values)
                    )
                    self.session.commit()
                    spider.logger.info(f"✅ 画像更新成功: '{raw_name}' (ID:{cat_id})")
                except Exception as e:
                    spider.logger.error(f"Category image update failed: {e}")
            else:
                spider.logger.warning(f"⚠️ DBにカテゴリが見つかりません: '{raw_name}' (Mapped: '{mapped_name}') - 画像保存をスキップ")
            
            return item

        # B. 車種データの更新（既存ロジック）
        bds_cat_name = item['category_name']
        model_names = item['model_names']
        
        mapped_name = self.CATEGORY_MAPPING.get(bds_cat_name, bds_cat_name)
        cat_id = self.cat_map.get(mapped_name)
        if not cat_id:
             cat_id = self.cat_map.get(self.normalize(mapped_name))
        
        if not cat_id: return item

        count = 0
        for raw in model_names:
            clean = re.sub(r'\s*[\(\uff08].*', '', raw).strip()
            norm = normalize_name(clean)
            disp = extract_displacement(raw)
            
            ids = self.model_cache.get(norm, [])
            for tid in ids:
                vals = {"category_id": cat_id}
                if disp:
                    curr = self.session.query(BikeModel).get(tid)
                    if curr and not curr.displacement:
                        vals["displacement"] = disp
                self.session.execute(update(BikeModel).where(BikeModel.id == tid).values(**vals))
                count += 1
        
        if count > 0:
            self.session.commit()
            
        return item

    def download_image(self, url, cat_id, spider):
        if not self.storage_base: return None
        try:
            ext = 'jpg'
            if '.png' in url: ext = 'png'
            elif '.gif' in url: ext = 'gif'
            elif '.webp' in url: ext = 'webp'
            
            rel_dir = "categories"
            abs_dir = os.path.join(self.storage_base, rel_dir)
            os.makedirs(abs_dir, exist_ok=True)
            
            filename = f"{cat_id}.{ext}"
            filepath = os.path.join(abs_dir, filename)
            rel_path = f"{rel_dir}/{filename}"

            headers = {"User-Agent": spider.settings.get('USER_AGENT')}
            res = requests.get(url, headers=headers, timeout=10)
            if res.status_code == 200:
                with open(filepath, 'wb') as f:
                    f.write(res.content)
                return rel_path
            return None
        except: return None

    def close_spider(self, spider):
        self.session.close()

# ==========================================
# 3. Spider
# ==========================================
class CategorySpider(scrapy.Spider):
    name = "bds_category_collector"
    allowed_domains = ["www.bds-bikesensor.net"]
    start_urls = ["https://www.bds-bikesensor.net/"]

    custom_settings = {
        'CONCURRENT_REQUESTS': 8,
        'DOWNLOAD_DELAY': 0.8,
        'COOKIES_ENABLED': False,
        'ITEM_PIPELINES': {'__main__.CategoryPipeline': 300},
        'USER_AGENT': MOTOHUB_USER_AGENT,
        'ROBOTSTXT_OBEY': True,
    }

    def parse(self, response):
        # 画像ボタンのブロックを取得
        buttons = response.css('.c-type_btn_wrap')
        self.logger.info(f"Found {len(buttons)} category buttons on top page.")
        
        for btn in buttons:
            link = btn.css('a.c-type_btn_link::attr(href)').get()
            
            # パーツカテゴリならスキップ
            if link and '/parts' in link:
                continue

            # 画像URL (data-src)
            img_url = btn.css('span.c-delay_img::attr(data-src)').get()
            
            # カテゴリ名の取得（強化版）
            # 1. alt属性から取得（これが一番確実）
            cat_name = btn.css('span.c-delay_img::attr(alt)').get()
            
            # 2. altが取れない場合、HTML文字列から正規表現で無理やり抜く（クォートなし対策）
            if not cat_name:
                btn_html = btn.get()
                # alt=ほげほげ または alt="ほげほげ" を抽出
                m = re.search(r'alt=["\']?([^"\' >]+)', btn_html)
                if m:
                    cat_name = m.group(1)

            # 3. それでもダメならテキストから抽出
            if not cat_name:
                text_parts = btn.css('.c-type_btn_text *::text').getall()
                raw_text = "".join([t.strip() for t in text_parts])
                cat_name = re.sub(r'\([\d,]+台\)', '', raw_text).strip()

            cat_name = cat_name.strip()

            self.logger.debug(f"Detected Category: {cat_name} (Link: {link})")

            if cat_name and img_url:
                yield {
                    'type': 'category_image',
                    'category_name': cat_name,
                    'image_url': response.urljoin(img_url)
                }

            if link:
                yield response.follow(
                    link,
                    callback=self.parse_category_page,
                    meta={'category_name': cat_name}
                )

    def parse_category_page(self, response):
        cat_name = response.meta['category_name']
        model_names = response.css(".c-search_name_block_text::text").getall()
        
        if model_names:
            yield {
                'type': 'model_list',
                'category_name': cat_name,
                'model_names': model_names
            }

if __name__ == "__main__":
    process = CrawlerProcess()
    process.crawl(CategorySpider)
    process.start()