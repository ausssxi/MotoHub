/**
 * MotoHub 相場ウィジェット
 * 
 * 使い方（外部サイト）:
 * <div id="motohub-price" data-model-id="123"></div>
 * <script src="https://www.motohub.jp/widget/price.js" async></script>
 * 
 * オプション属性:
 *   data-model-id  : 車種ID（必須）
 *   data-theme     : "light" (デフォルト) or "dark"
 *   data-show-resale: "true" (デフォルト) or "false"
 */
(function() {
    'use strict';

    var API_BASE = 'https://www.motohub.jp';
    var SITE_URL = 'https://www.motohub.jp';

    // ウィジェットコンテナを探す
    var containers = document.querySelectorAll('[id="motohub-price"], [data-motohub-widget="price"]');
    
    if (!containers.length) return;

    containers.forEach(function(container) {
        var modelId = container.getAttribute('data-model-id');
        if (!modelId) {
            container.innerHTML = '<p style="color:#999;font-size:12px;">data-model-id が指定されていません</p>';
            return;
        }

        var theme = container.getAttribute('data-theme') || 'light';
        var showResale = container.getAttribute('data-show-resale') !== 'false';
        // バイク店向け（任意）: 在庫ページへのCTAを追加。未指定（ブロガー）の場合は従来表示のまま
        var shopName = container.getAttribute('data-shop-name') || '';
        var shopUrl = container.getAttribute('data-shop-url') || '';

        // ローディング表示
        container.innerHTML = '<div style="text-align:center;padding:20px;color:#999;font-size:12px;">読み込み中...</div>';

        // APIからデータ取得
        fetch(API_BASE + '/api/widget/price/' + modelId)
            .then(function(res) {
                if (!res.ok) throw new Error('API error');
                return res.json();
            })
            .then(function(data) {
                container.innerHTML = renderWidget(data, theme, showResale, shopName, shopUrl);
            })
            .catch(function() {
                container.innerHTML = '<div style="text-align:center;padding:20px;color:#999;font-size:12px;">データを取得できませんでした</div>';
            });
    });

    function renderWidget(data, theme, showResale, shopName, shopUrl) {
        var isDark = theme === 'dark';
        var bg = isDark ? '#1a1a2e' : '#ffffff';
        var text = isDark ? '#e0e0e0' : '#1f2937';
        var subtext = isDark ? '#9ca3af' : '#6b7280';
        var border = isDark ? '#2d2d44' : '#e5e7eb';
        var accent = '#2563eb';
        var priceColor = isDark ? '#fbbf24' : '#d97706';
        var cardBg = isDark ? '#16162a' : '#f9fafb';

        var detailUrl = SITE_URL + data.seo_url;
        var hasStats = data.stats && data.stats.count > 0 && data.stats.avg;
        var hasResale = showResale && data.resale && data.resale.data_count > 0 && data.resale.min;

        var html = '';
        html += '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;max-width:400px;background:' + bg + ';border:1px solid ' + border + ';border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);">';
        
        // ヘッダー
        html += '<div style="padding:16px 20px;border-bottom:1px solid ' + border + ';">';
        html += '<div style="font-size:10px;font-weight:700;color:' + accent + ';text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">' + escapeHtml(data.manufacturer_name) + '</div>';
        html += '<div style="font-size:18px;font-weight:900;color:' + text + ';line-height:1.2;">' + escapeHtml(data.model_name) + '</div>';
        if (data.displacement) {
            html += '<div style="font-size:11px;color:' + subtext + ';margin-top:2px;">' + data.displacement + 'cc</div>';
        }
        html += '</div>';

        // 価格情報
        html += '<div style="padding:16px 20px;">';
        
        if (hasStats) {
            html += '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px;">';
            html += priceBox('平均', data.stats.avg, cardBg, text, subtext);
            html += priceBox('最安', data.stats.min, cardBg, accent, subtext);
            html += priceBox('最高', data.stats.max, cardBg, '#ef4444', subtext);
            html += '</div>';
            html += '<div style="font-size:10px;color:' + subtext + ';text-align:center;">現在 ' + data.stats.count + '台が市場に流通</div>';
        } else {
            html += '<div style="text-align:center;padding:12px;background:' + cardBg + ';border-radius:8px;font-size:12px;color:' + subtext + ';">価格データ収集中</div>';
        }

        // 買取相場
        if (hasResale) {
            html += '<div style="margin-top:12px;padding:12px;background:linear-gradient(135deg,' + (isDark ? '#2d2a1a' : '#fffbeb') + ',' + (isDark ? '#2a2d1a' : '#fef3c7') + ');border-radius:10px;text-align:center;">';
            html += '<div style="font-size:10px;font-weight:700;color:' + priceColor + ';margin-bottom:4px;">想定買取価格</div>';
            html += '<div style="font-size:22px;font-weight:900;color:' + priceColor + ';">' + data.resale.min + '<span style="font-size:12px;color:' + subtext + ';"> ~ </span>' + data.resale.max + '<span style="font-size:12px;font-weight:700;color:' + subtext + ';"> 万円</span></div>';
            html += '</div>';
        }

        html += '</div>';

        // バイク店CTA（任意）— data-shop-url がある場合のみ追加。無い場合は以下を一切出力せず従来通り
        var safeShopUrl = sanitizeUrl(shopUrl);
        if (safeShopUrl) {
            var ctaLabel = (shopName ? escapeHtml(shopName) + 'の' : '') + '在庫を見る →';
            html += '<div style="padding:0 20px 16px;">';
            html += '<a href="' + safeShopUrl + '" target="_blank" rel="noopener nofollow" style="display:block;text-align:center;background:' + accent + ';color:#ffffff;font-size:13px;font-weight:800;padding:12px;border-radius:10px;text-decoration:none;box-shadow:0 1px 2px rgba(0,0,0,0.1);">' + ctaLabel + '</a>';
            html += '</div>';
        }

        // フッター（被リンク！）
        html += '<div style="padding:12px 20px;border-top:1px solid ' + border + ';display:flex;align-items:center;justify-content:space-between;">';
        html += '<a href="' + detailUrl + '" target="_blank" rel="noopener" style="font-size:11px;font-weight:700;color:' + accent + ';text-decoration:none;">詳細を見る →</a>';
        html += '<a href="' + SITE_URL + '" target="_blank" rel="noopener" style="font-size:10px;font-weight:600;color:' + subtext + ';text-decoration:none;display:flex;align-items:center;gap:4px;">';
        html += '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>';
        html += 'Powered by MotoHub</a>';
        html += '</div>';

        html += '</div>';

        // 更新日
        html += '<div style="text-align:right;font-size:9px;color:#9ca3af;margin-top:4px;padding-right:4px;max-width:400px;">更新: ' + data.updated_at + '</div>';
        return '<div style="display:block;">' + html + '</div>';
    }

    function priceBox(label, value, bg, valueColor, labelColor) {
        return '<div style="background:' + bg + ';border-radius:8px;padding:8px 4px;text-align:center;">'
            + '<div style="font-size:9px;font-weight:700;color:' + labelColor + ';">' + label + '</div>'
            + '<div style="font-size:18px;font-weight:900;color:' + valueColor + ';line-height:1.2;">' + value + '<span style="font-size:10px;">万</span></div>'
            + '</div>';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // 外部入力のURLを安全化: http/https のみ許可（javascript: 等を遮断）し、属性用にエスケープ
    function sanitizeUrl(u) {
        if (!u) return '';
        var t = String(u).trim();
        if (!/^https?:\/\//i.test(t)) return '';
        return escapeHtml(t);
    }
})();