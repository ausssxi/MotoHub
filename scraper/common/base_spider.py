import scrapy
import datetime
from sqlalchemy import update, or_
from common.database import SessionLocal, Site, Listing, PriceHistory

# User-Agent の定義は common/user_agent.py に一元化した（scrapy 非依存の軽量モジュール）。
# ここでは後方互換のため再エクスポートする（既存17スパイダーの
# `from common.base_spider import ..., MOTOHUB_USER_AGENT` はそのまま動く）。
from common.user_agent import MOTOHUB_USER_AGENT

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
            description=data.get('description'),
            image_urls=data.get('image_urls', []),
            has_repair_history=data.get('has_repair_history', False),
            is_sold_out=False,
            last_seen_at=datetime.datetime.now(),
            needs_reindex=True
        )
        self.db.add(new_listing)

    # source_url（spread ID）が別車両に使い回されたとき、車両の同一性に関わる列を上書きする対象。
    # manufacturer_id / category_id / displacement は含めない（スクレイパーが持たず、Laravel が bike_model から導出）。
    UPDATABLE_ON_REUSE = ['title', 'bike_model_id', 'model_year', 'mileage',
                          'condition', 'has_repair_history']

    def update_listing(self, url, data):
        """既存の出品情報を更新（価格変動があれば履歴も記録）"""
        # 価格変動の検知＋bike_model_id変化判定のため、更新前の値を取得
        existing = self.db.query(Listing.id, Listing.total_price, Listing.bike_model_id).filter(
            Listing.source_url == url
        ).first()

        if existing:
            old_price = int(existing.total_price or 0)
            new_price = int(data.get('total_price') or 0)
            if old_price > 0 and new_price > 0 and new_price != old_price:
                self.db.add(PriceHistory(
                    listing_id=existing.id,
                    old_price=old_price,
                    new_price=new_price,
                    is_notified=False,
                ))

        update_values = {
            "price": data.get('price'),
            "total_price": data.get('total_price'),
            "description": data.get('description'),
            "is_sold_out": False,
            "last_seen_at": datetime.datetime.now(),
            "needs_reindex": True,
            "updated_at": datetime.datetime.now()
        }

        # 車両入れ替え（source_url 使い回し）対策: 同一性に関わる列を、キーが存在し値が None でないものだけ上書き。
        # 真偽判定は不可（has_repair_history=False / mileage=0 は正当な値）。必ず is not None で判定する。
        for key in self.UPDATABLE_ON_REUSE:
            if key in data and data[key] is not None:
                update_values[key] = data[key]

        # bike_model_id が実際に変わった場合のみ、導出列（manufacturer_id/category_id/displacement）が
        # 古くなるため manufacturer_id を NULL に戻し、OptimizeSearchData（whereNull で拾う）に再導出させる。
        if (
            'bike_model_id' in update_values
            and existing is not None
            and update_values['bike_model_id'] != existing.bike_model_id
        ):
            update_values['manufacturer_id'] = None

        self.db.query(Listing).filter(Listing.source_url == url).update(update_values)

    def handle_sold_out(self):
        """last_seen_atが72時間以上前のアクティブなlistingsをsold_outにする"""
        cutoff = datetime.datetime.now() - datetime.timedelta(hours=72)

        stale = self.db.query(Listing.id).filter(
            Listing.site_id == self.site_id,
            Listing.is_sold_out == False,
            Listing.last_seen_at != None,
            Listing.last_seen_at < cutoff,
        ).all()

        if not stale:
            self.logger.info(f"No stale listings to mark as SOLD OUT for {self.site_name}")
            return

        stale_ids = [row.id for row in stale]
        self.logger.info(f"Marking {len(stale_ids)} listings as SOLD OUT for {self.site_name} (last_seen > 72h)")

        for i in range(0, len(stale_ids), 500):
            chunk = stale_ids[i:i + 500]
            self.db.query(Listing).filter(
                Listing.id.in_(chunk),
            ).update({
                "is_sold_out": True,
                "needs_reindex": True,
                "updated_at": datetime.datetime.now(),
            }, synchronize_session=False)
            self.db.commit()

    def closed(self, reason):
        """スパイダー終了時にDB接続を閉じる"""
        # 店舗コレクターの実行サマリ: 取得元サイトのテスト店を何件弾いたか。
        # ShopManager を使わないスパイダー（出品・車種収集など）では何も出さない。
        shop_manager = getattr(self, 'shop_manager', None)
        if shop_manager is not None:
            self.logger.info(
                f"Excluded test shops: {shop_manager.excluded_count} "
                f"(by identifier: {shop_manager.excluded_by_identifier}, "
                f"by name: {shop_manager.excluded_by_name})"
            )

        self.db.close()
        self.logger.info(f"Spider closed: {reason}")