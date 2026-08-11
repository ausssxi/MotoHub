import re
import logging
from sqlalchemy import or_
from .database import Shop, ShopIdentifier
from .shop_exclusions import find_exclusion
from utils import normalize_prefecture

class ShopManager:
    """
    店舗の名寄せ（重複排除）と保存を管理するクラス。
    電話番号や店名・住所を基に、複数サイトにまたがる同一店舗を特定します。

    取得元サイトのテスト店は common/shop_exclusions.py の定義に従いここで弾く
    （3つの shop_collector が共有する唯一の保存入口なので、1か所で防げる）。
    site_name は除外判定のキーに使う。省略した場合は識別子による除外が効かず、
    店名パターンによる除外だけが効く。
    """
    def __init__(self, db_session, site_name=None):
        self.db = db_session
        self.logger = logging.getLogger(__name__)
        self.site_name = site_name
        # 実行サマリ用の除外カウンタ
        self.excluded_by_identifier = 0
        self.excluded_by_name = 0

    @property
    def excluded_count(self):
        """このクロールで除外したテスト店の総数"""
        return self.excluded_by_identifier + self.excluded_by_name

    def get_or_create_shop(self, site_id, site_shop_id, data):
        """
        サイト固有の店舗ID、または電話番号等から既存店舗を特定し、
        必要であれば新規作成・識別子の紐付けを行います。

        取得元サイトのテスト店と判定した場合は、保存も名寄せも行わず None を返します。
        （呼び出し側は返り値が None のとき画像パイプラインへも渡しません）
        """
        # 取得元サイトのテスト店を除外する。DBに一切触れる前に判定する。
        exclusion = find_exclusion(self.site_name, site_shop_id, data.get('name'))
        if exclusion:
            if exclusion['rule'] == 'identifier':
                self.excluded_by_identifier += 1
            else:
                self.excluded_by_name += 1

            # 黙って捨てると後から「なぜ無いのか」が追えなくなるので必ず残す。
            # 店名で弾いた場合も識別子を併記し、除外リストへ追記できるようにする。
            self.logger.warning(
                "[shop-excluded] rule=%s site=%s identifier=%s name=%r detail=%s",
                exclusion['rule'],
                self.site_name,
                site_shop_id,
                data.get('name'),
                exclusion['detail'],
            )
            return None

        # 都道府県を正式名称に正規化（重複判定・保存の両方で表記揺れを吸収する）
        data['prefecture'] = normalize_prefecture(data.get('prefecture'))

        # 1. 識別子テーブルでチェック (すでに紐付け済みか)
        identifier = self.db.query(ShopIdentifier).filter(
            ShopIdentifier.site_id == site_id,
            ShopIdentifier.identifier == str(site_shop_id)
        ).first()

        if identifier:
            # 既存店舗が見つかった場合、情報を更新して返す
            shop = self.db.query(Shop).get(identifier.shop_id)
            if shop:
                self._update_shop_info(shop, data)
                return shop

        # 2. 電話番号でチェック (他サイト経由で登録済みか)
        # ※ source='scraper' に限定。ユーザー投稿店(source='user')は電話一致でも触れない。
        normalized_phone = self._normalize_phone(data.get('phone'))
        if normalized_phone:
            shop = self.db.query(Shop).filter(
                Shop.phone == normalized_phone,
                Shop.source == 'scraper'
            ).first()
            if shop:
                self._create_identifier(shop.id, site_id, site_shop_id)
                self._update_shop_info(shop, data)
                return shop

        # （上記2分岐は _update_shop_info 内で service_tags を上書き済み）

        # 3. 店名と都道府県でチェック (最終手段)
        # ※ source='scraper' に限定。ユーザー投稿店は店名一致でも触れない。
        shop = self.db.query(Shop).filter(
            Shop.name == data['name'],
            Shop.prefecture == data.get('prefecture'),
            Shop.source == 'scraper'
        ).first()

        if shop:
            self._create_identifier(shop.id, site_id, site_shop_id)
            self._apply_service_tags(shop, data)
            return shop

        # 4. 全く新しい店舗として登録
        new_shop = Shop(
            name=data['name'],
            prefecture=data.get('prefecture'),
            address=data.get('address'),
            phone=normalized_phone,
            website_url=data.get('website_url'),
            business_hours=data.get('business_hours'),
            regular_holiday=data.get('regular_holiday'),
            image_url=data.get('image_url'),
            service_tags=data.get('service_tags')
        )
        self.db.add(new_shop)
        self.db.flush() # ID確定のためにフラッシュ

        self._create_identifier(new_shop.id, site_id, site_shop_id)
        return new_shop

    def _update_shop_info(self, shop, data):
        """不足している情報を補完（service_tagsのみ常に上書き）"""
        if not shop.address and data.get('address'): shop.address = data['address']
        if not shop.phone and data.get('phone'): shop.phone = self._normalize_phone(data['phone'])
        if not shop.website_url and data.get('website_url'): shop.website_url = data['website_url']
        self._apply_service_tags(shop, data)

    def _apply_service_tags(self, shop, data):
        """バッジ（service_tags）は鮮度が重要なため、提供された場合は常に上書きする。
        非Webikeのクロールでは data に service_tags が無いため、既存値は保持される。"""
        if data.get('service_tags') is not None:
            shop.service_tags = data['service_tags']

    def _create_identifier(self, shop_id, site_id, identifier):
        """サイト固有IDとの紐付けを保存"""
        new_ident = ShopIdentifier(
            shop_id=shop_id,
            site_id=site_id,
            identifier=str(identifier)
        )
        self.db.add(new_ident)

    def _normalize_phone(self, phone):
        """電話番号の正規化 (ハイフン除去)"""
        if not phone: return None
        return re.sub(r'\D', '', str(phone))