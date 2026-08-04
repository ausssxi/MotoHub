/**
 * POI表示名フォールバック（riders/map.js と blog/embedded-map.js の共通実装）。
 * DBのセンチネル値を廃止し、name が null/空でも必ず有意な表示名を返す。
 * 順序: item.name → 種別別の代替（pois=brand / レンタルガレージ=operator / ショップ=shop_name / 記事=title）
 *       → 種別名（空文字は返さない）。
 *
 * ※ 各マップJSより前に読み込むこと（window.MotoHub.resolvePoiName を提供）。
 */
(function () {
    'use strict';

    window.MotoHub = window.MotoHub || {};

    var LAYER_TYPE_LABEL = {
        shop: 'ショップ',
        parking: '駐車場',
        gas_station: 'ガソリンスタンド',
        convenience_store: 'コンビニ',
        car_wash: '洗車場',
        michi_no_eki: '道の駅',
        rental_garage: 'レンタルガレージ',
        blog: '記事',
        saved_spots: 'お気に入り',
    };

    window.MotoHub.poiTypeLabel = LAYER_TYPE_LABEL;

    window.MotoHub.resolvePoiName = function (layerKey, item) {
        item = item || {};
        if (item.name) return item.name;

        var alt = null;
        if (layerKey === 'shop') alt = item.shop_name;
        else if (layerKey === 'rental_garage') alt = item.operator;
        else if (layerKey === 'gas_station' || layerKey === 'convenience_store' || layerKey === 'car_wash') alt = item.brand;
        else if (layerKey === 'blog') alt = item.title;
        if (alt) return alt;

        return LAYER_TYPE_LABEL[layerKey] || '';
    };
})();
