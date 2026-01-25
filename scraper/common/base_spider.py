import scrapy
import datetime
from sqlalchemy import update, or_
from common.database import SessionLocal, Site, Listing

# もし ShopManager を使っていない場合は削除してください（環境に合わせて調整）
# from common.shop_manager import ShopManager

class BaseBikeSpider(scrapy.Spider):
    """
    MotoHub 全てのスクレイパーの親クラス。
    共通のDB操作（保存、更新、完売判定、重複チェック）を提供します。
    """
    site_name = None

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.db = SessionLocal()
        
        if not self.site_name:
            raise ValueError("site_name must be defined in child spider class.")
            
        # サイト情報の取得
        site = self.db.query(Site).filter(Site.name == self.site_name).first()
        if not site:
            self.logger.error(f"Site '{self.site_name}' not found.")
            self.site_id = 0
        else:
            self.site_id = site.id

        # ShopManagerが必要な場合はここで行う（不要なら削除）
        # self.shop_manager = ShopManager(self.db)

        # 全サイトの「出品中」データをメモリに保持（クロスサイト重複チェック用）
        self.active_listings = self.db.query(
            Listing.shop_id, Listing.bike_model_id, Listing.model_year, Listing.mileage
        ).filter(Listing.is_sold_out == False).all()

        self.found_urls = set()

    def is_cross_site_duplicate(self, data):
        """他サイトとの重複（同一店舗・同一車両）をチェック"""
        if not data.get('shop_id'):
            return False
            
        return any(
            l.shop_id == data['shop_id'] and 
            l.bike_model_id == data['bike_model_id'] and
            l.model_year == data['model_year'] and
            l.mileage == data['mileage']
            for l in self.active_listings
        )

    def save_listing(self, data):
        """出品情報を新規保存"""
        new_listing = Listing(
            bike_model_id=data['bike_model_id'],
            shop_id=data.get('shop_id'),
            site_id=self.site_id,
            title=data.get('title'),
            source_url=data['source_url'],
            price=data.get('price'),
            total_price=data.get('total_price'),
            model_year=data.get('model_year'),
            mileage=data.get('mileage'),
            condition=data.get('condition', '中古車'),
            # ✨ 追加: 説明文を保存
            description=data.get('description'),
            image_urls=data.get('image_urls', []),
            has_repair_history=data.get('has_repair_history', False),
            is_sold_out=False
        )
        self.db.add(new_listing)

    def update_listing(self, url, data):
        """既存の出品情報を更新"""
        update_values = {
            "price": data.get('price'),
            "total_price": data.get('total_price'),
            # ✨ 追加: 説明文も更新対象にする（変更されている可能性があるため）
            "description": data.get('description'),
            "is_sold_out": False,
            "updated_at": datetime.datetime.now()
        }
        self.db.query(Listing).filter(Listing.source_url == url).update(update_values)

    def handle_sold_out(self, known_urls):
        """今回見つからなかったURLを完売（is_sold_out=True）にする"""
        missing_urls = known_urls - self.found_urls
        if missing_urls:
            missing_list = list(missing_urls)
            self.logger.info(f"Marking {len(missing_list)} listings as SOLD OUT for {self.site_name}")
            for i in range(0, len(missing_list), 500):
                chunk = missing_list[i:i + 500]
                self.db.query(Listing).filter(
                    Listing.source_url.in_(chunk), 
                    Listing.site_id == self.site_id
                ).update({
                    "is_sold_out": True, 
                    "updated_at": datetime.datetime.now()
                }, synchronize_session=False)
                self.db.commit()

    def closed(self, reason):
        """スパイダー終了時にDB接続を閉じる"""
        self.db.close()
        self.logger.info(f"Spider closed: {reason}")