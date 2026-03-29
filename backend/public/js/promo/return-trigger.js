/**
 * 再訪促進ボトムシート
 * 未ログインユーザーの2ページ目閲覧時に8秒後に表示。
 * 3日間のcooldownをlocalStorageで管理。
 */
(function() {
    'use strict';

    const COOLDOWN_KEY = 'motohub_return_trigger_ts';
    const DISMISS_KEY = 'line_banner_dismissed_at';
    const DISMISS_DAYS = 7;
    const COOLDOWN_DAYS = 3;
    const DELAY_MS = 8000;

    function isDismissed() {
        var ts = localStorage.getItem(DISMISS_KEY);
        return ts && (Date.now() - parseInt(ts, 10)) < DISMISS_DAYS * 24 * 60 * 60 * 1000;
    }

    function shouldShow() {
        // ログイン済みなら非表示
        if (document.body.dataset.loggedIn === 'true') return false;

        // line_banner_dismissed_at で7日間非表示
        if (isDismissed()) return false;

        // cooldownチェック
        const lastShown = localStorage.getItem(COOLDOWN_KEY);
        if (lastShown) {
            const elapsed = Date.now() - parseInt(lastShown, 10);
            if (elapsed < COOLDOWN_DAYS * 24 * 60 * 60 * 1000) return false;
        }

        // 会員登録ポップアップと同時表示しない
        if (document.getElementById('promo-pageview-popup')) return false;

        // 2ページ目以降かチェック（sessionStorageでPVカウント）
        let pv = parseInt(sessionStorage.getItem('motohub_pv') || '0', 10);
        pv++;
        sessionStorage.setItem('motohub_pv', String(pv));
        return pv >= 2;
    }

    function getContext() {
        const h1 = document.querySelector('h1');
        if (h1 && h1.textContent.trim().length > 0) {
            // テキストだけ抽出（子要素のテキストも含む）、長すぎたらカット
            let text = h1.textContent.trim().replace(/\s+/g, ' ');
            if (text.length > 30) text = text.substring(0, 30) + '…';
            return text;
        }
        return 'お気に入りバイク';
    }

    function createSheet() {
        const context = getContext();

        const sheet = document.createElement('div');
        sheet.id = 'return-trigger-sheet';
        sheet.style.cssText = 'position:fixed;bottom:0;left:0;right:0;z-index:9999;padding:0 16px 16px;pointer-events:none;';
        sheet.innerHTML = `
            <div style="pointer-events:auto;max-width:480px;margin:0 auto;background:#fff;border-radius:20px 20px 0 0;box-shadow:0 -4px 30px rgba(0,0,0,.12);padding:24px 20px 20px;animation:slideUp .4s ease-out;position:relative;" id="return-trigger-inner">
                <button id="return-trigger-close" style="position:absolute;top:12px;right:12px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;background:#f3f4f6;border:none;border-radius:50%;cursor:pointer;transition:background .2s;" onmouseenter="this.style.background='#e5e7eb'" onmouseleave="this.style.background='#f3f4f6'" aria-label="閉じる"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2.5" stroke-linecap="round"><path d="M6 18L18 6M6 6l12 12"/></svg></button>
                <div style="text-align:center;">
                    <p style="font-size:13px;font-weight:900;color:#1f2937;margin:0 0 4px;">${context}の価格変動をLINEでお届け</p>
                    <p style="font-size:11px;color:#6b7280;margin:0 0 16px;">値下げ・新着をリアルタイムで通知。もう見逃さない！</p>
                    <a href="/auth/line/redirect" style="display:inline-flex;align-items:center;gap:8px;background:#06C755;color:#fff;font-weight:900;font-size:14px;padding:12px 28px;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px rgba(6,199,85,.3);transition:opacity .2s;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 5.88 2 10.54c0 4.07 3.42 7.49 8.05 8.44.31.07.73.21.84.48.1.25.06.63.03.88l-.14.83c-.04.25-.2.97.85.53s5.61-3.31 7.66-5.67C21.03 13.86 22 12.28 22 10.54 22 5.88 17.52 2 12 2z"/></svg>
                        LINEで通知を受け取る
                    </a>
                </div>
            </div>
        `;

        document.body.appendChild(sheet);

        // 閉じるボタン
        document.getElementById('return-trigger-close').addEventListener('click', function() {
            localStorage.setItem(COOLDOWN_KEY, String(Date.now()));
            localStorage.setItem(DISMISS_KEY, String(Date.now()));
            sheet.style.transition = 'opacity .3s, transform .3s';
            sheet.style.opacity = '0';
            sheet.style.transform = 'translateY(100%)';
            setTimeout(function() { sheet.remove(); }, 300);
        });
    }

    // エントリーポイント
    if (shouldShow()) {
        setTimeout(function() {
            // 表示直前に再チェック（8秒の間にポップアップが出た可能性）
            if (isDismissed() || document.getElementById('promo-pageview-popup')) return;
            createSheet();
        }, DELAY_MS);
    }
})();
