/**
 * 離脱防止エンゲージメントバナー
 * 車両詳細・車種詳細ページで通知購読を促すバナーを表示。
 * トリガー: 30秒経過 or スクロール50%（いずれか先）
 * 1セッション1回のみ表示。
 */
(function () {
    'use strict';

    var SESSION_KEY = 'motohub_engagement_banner_shown';
    var DISMISS_KEY = 'line_banner_dismissed_at';
    var DISMISS_DAYS = 7;
    var TIMER_MS = 30000;
    var SCROLL_THRESHOLD = 0.5;

    // ---- ガード ----

    function isDismissed() {
        var ts = localStorage.getItem(DISMISS_KEY);
        if (!ts) return false;
        return (Date.now() - parseInt(ts, 10)) < DISMISS_DAYS * 24 * 60 * 60 * 1000;
    }

    function shouldShow() {
        if (!window.__bikeModelId) return false;
        if (document.body.dataset.loggedIn === 'true') return false;
        if (isDismissed()) return false;
        if (sessionStorage.getItem(SESSION_KEY)) return false;
        if (typeof MotoHubPush !== 'undefined' && MotoHubPush.isSubscribed(window.__bikeModelId)) return false;
        return true;
    }

    function otherPromoVisible() {
        return !!(document.getElementById('return-trigger-sheet') || document.getElementById('promo-pageview-popup'));
    }

    // ---- スタイル注入 ----

    function injectStyles() {
        if (document.getElementById('engagement-banner-styles')) return;
        var style = document.createElement('style');
        style.id = 'engagement-banner-styles';
        style.textContent =
            '@keyframes engageBannerSlideUp{from{transform:translateY(100%);opacity:0}to{transform:translateY(0);opacity:1}}' +
            '@keyframes engageBannerFadeOut{from{opacity:1}to{opacity:0;transform:translateY(20px)}}';
        document.head.appendChild(style);
    }

    // ---- バナー構築 ----

    function getModelName() {
        var h1 = document.querySelector('h1');
        if (h1) {
            var text = h1.textContent.trim().replace(/\s+/g, ' ');
            return text.length > 25 ? text.substring(0, 25) + '…' : text;
        }
        return 'このバイク';
    }

    function isMobile() {
        return window.innerWidth < 768;
    }

    function buildBanner() {
        var support = (typeof MotoHubPush !== 'undefined' && MotoHubPush.getNotificationSupport)
            ? MotoHubPush.getNotificationSupport()
            : 'unsupported';

        var modelName = getModelName();
        var mobile = isMobile();
        var bikeModelId = window.__bikeModelId;

        var wrapper = document.createElement('div');
        wrapper.id = 'engagement-banner';
        wrapper.style.cssText = 'position:fixed;z-index:60;'
            + (mobile
                ? 'bottom:80px;left:0;right:0;padding:0 8px;'
                : 'bottom:24px;right:24px;max-width:420px;width:100%;');

        var card = document.createElement('div');
        card.style.cssText = 'background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.15);padding:20px;position:relative;animation:engageBannerSlideUp .4s ease-out;';

        // 閉じるボタン
        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', '閉じる');
        closeBtn.style.cssText = 'position:absolute;top:10px;right:10px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;background:#f3f4f6;border:none;border-radius:50%;cursor:pointer;transition:background .2s;';
        closeBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2.5" stroke-linecap="round"><path d="M6 18L18 6M6 6l12 12"/></svg>';
        closeBtn.onmouseenter = function() { this.style.background = '#e5e7eb'; };
        closeBtn.onmouseleave = function() { this.style.background = '#f3f4f6'; };
        closeBtn.addEventListener('click', function () { dismiss(wrapper); });
        card.appendChild(closeBtn);

        // ヘッダー
        var heading = document.createElement('p');
        heading.style.cssText = 'font-size:14px;font-weight:800;color:#1f2937;margin:0 0 4px;padding-right:24px;';
        heading.textContent = modelName + 'の値下げ・新着情報';
        card.appendChild(heading);

        var sub = document.createElement('p');
        sub.style.cssText = 'font-size:12px;color:#6b7280;margin:0 0 14px;';
        sub.textContent = '通知をオンにして、お得なタイミングを逃さない！';
        card.appendChild(sub);

        // デバイス別コンテンツ
        if (support === 'supported') {
            var btn = createPushButton(bikeModelId, card, wrapper);
            card.appendChild(btn);
        } else if (support === 'ios-safari') {
            card.appendChild(createIOSGuide());
        } else {
            card.appendChild(createLINEButton());
        }

        wrapper.appendChild(card);
        return wrapper;
    }

    function createPushButton(bikeModelId, card, wrapper) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.style.cssText = 'display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px;border:none;border-radius:12px;font-size:14px;font-weight:700;color:#fff;cursor:pointer;background:linear-gradient(135deg,#3b82f6,#2563eb);box-shadow:0 4px 14px rgba(59,130,246,.3);transition:opacity .2s;';
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg><span>通知を受け取る</span>';

        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.querySelector('span').textContent = '処理中...';

            MotoHubPush.subscribe(bikeModelId).then(function () {
                showSuccess(card, wrapper);
            }).catch(function (e) {
                btn.disabled = false;
                btn.querySelector('span').textContent = e.message || '通知を受け取る';
            });
        });

        return btn;
    }

    function createIOSGuide() {
        var div = document.createElement('div');
        div.style.cssText = 'background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:12px;text-align:center;';
        div.innerHTML = '<p style="font-size:13px;font-weight:700;color:#1e40af;margin:0 0 4px;">📲 ホーム画面に追加で通知ON</p>'
            + '<p style="font-size:11px;color:#3b82f6;margin:0;">'
            + '<svg style="width:14px;height:14px;display:inline-block;vertical-align:text-bottom;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>'
            + ' をタップ → 「ホーム画面に追加」</p>';
        return div;
    }

    function createLINEButton() {
        var a = document.createElement('a');
        a.href = '/auth/line/redirect';
        a.style.cssText = 'display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px;border:none;border-radius:12px;font-size:14px;font-weight:700;color:#fff;text-decoration:none;background:#06C755;box-shadow:0 4px 14px rgba(6,199,85,.3);transition:opacity .2s;';
        a.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 5.88 2 10.54c0 4.07 3.42 7.49 8.05 8.44.31.07.73.21.84.48.1.25.06.63.03.88l-.14.83c-.04.25-.2.97.85.53s5.61-3.31 7.66-5.67C21.03 13.86 22 12.28 22 10.54 22 5.88 17.52 2 12 2z"/></svg><span>LINEで通知を受け取る</span>';
        return a;
    }

    // ---- 成功表示 ----

    function showSuccess(card, wrapper) {
        card.innerHTML = '';
        card.style.cssText += 'text-align:center;padding:24px 20px;';

        var check = document.createElement('div');
        check.style.cssText = 'width:48px;height:48px;border-radius:50%;background:#22c55e;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;';
        check.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
        card.appendChild(check);

        var msg = document.createElement('p');
        msg.style.cssText = 'font-size:14px;font-weight:700;color:#1f2937;margin:0;';
        msg.textContent = '通知をオンにしました！';
        card.appendChild(msg);

        window.dispatchEvent(new CustomEvent('push-subs-changed'));

        setTimeout(function () {
            wrapper.style.animation = 'engageBannerFadeOut .3s ease-in forwards';
            setTimeout(function () { wrapper.remove(); }, 300);
        }, 3000);
    }

    // ---- 表示 / 非表示 ----

    function dismiss(wrapper) {
        sessionStorage.setItem(SESSION_KEY, '1');
        localStorage.setItem(DISMISS_KEY, Date.now().toString());
        wrapper.style.animation = 'engageBannerFadeOut .3s ease-in forwards';
        setTimeout(function () { wrapper.remove(); }, 300);
    }

    function showBanner() {
        if (!shouldShow()) return;
        if (otherPromoVisible()) return;
        if (document.getElementById('engagement-banner')) return;

        sessionStorage.setItem(SESSION_KEY, '1');
        injectStyles();
        var banner = buildBanner();
        document.body.appendChild(banner);
    }

    // ---- トリガー ----

    var triggered = false;

    function trigger() {
        if (triggered) return;
        triggered = true;
        showBanner();
    }

    // タイマー
    var timer = setTimeout(trigger, TIMER_MS);

    // スクロール
    function onScroll() {
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        if (docHeight > 0 && (scrollTop / docHeight) >= SCROLL_THRESHOLD) {
            trigger();
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });

    // トリガー後にリスナー解除
    var origTrigger = trigger;
    trigger = function () {
        origTrigger();
        clearTimeout(timer);
        window.removeEventListener('scroll', onScroll);
    };
})();
