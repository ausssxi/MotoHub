/**
 * MotoHub Review Component Logic
 * レビューのモーダル制御、星評価UI、Ajax送信を担当します。
 */

let reviewRating = 5;

// グローバルスコープに公開（HTMLのonclickから呼ばれるため）
window.openReviewModal = function() {
    const modal = document.getElementById('review-modal');
    const content = document.getElementById('review-modal-content');
    if (!modal || !content) return;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }, 10);
};

window.closeReviewModal = function() {
    const modal = document.getElementById('review-modal');
    const content = document.getElementById('review-modal-content');
    if (!modal || !content) return;

    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 300);
};

// 投稿成功後、画面のリストに新しいレビューを挿入する関数
function appendReviewToList(review) {
    if (!review) return;
    const container = document.getElementById('review-list-container');
    if (!container) return;

    const noMsg = document.getElementById('no-review-msg');
    if (noMsg) noMsg.remove();

    let starsHtml = '';
    for(let i=1; i<=5; i++) {
        starsHtml += `<i data-lucide="star" class="w-3.5 h-3.5 ${i <= review.rating ? 'fill-current' : 'text-gray-300'}"></i>`;
    }

    // 項目別評価HTML生成
    const detailLabels = {
        rating_design: 'デザイン',
        rating_engine: 'エンジン',
        rating_handling: '取り回し',
        rating_fuel_economy: '燃費',
        rating_cost_performance: 'コスパ',
    };
    let detailHtml = '';
    const detailItems = [];
    for (const [key, label] of Object.entries(detailLabels)) {
        const val = parseInt(review[key]);
        if (val > 0) {
            detailItems.push(`<span class="text-[10px] font-bold text-gray-500">${label}<span class="text-yellow-500 ml-0.5">${'★'.repeat(val)}</span></span>`);
        }
    }
    if (detailItems.length > 0) {
        detailHtml = `<div class="flex flex-wrap gap-x-3 gap-y-1 mb-3">${detailItems.join('')}</div>`;
    }

    const html = `
        <div class="p-4 bg-yellow-50/80 border-yellow-200 rounded-2xl border transition-all animate-in fade-in slide-in-from-top-4 duration-500 shadow-sm">
            <div class="flex justify-between items-start mb-2">
                <div class="flex items-center gap-2">
                    <div class="flex text-yellow-400">
                        ${starsHtml}
                    </div>
                    <span class="text-xs font-black text-gray-800">${review.title}</span>
                    <span class="text-[9px] font-black bg-yellow-400 text-yellow-900 px-1.5 py-0.5 rounded ml-2 shadow-sm">NEW</span>
                </div>
            </div>
            <p class="text-xs text-gray-700 leading-relaxed line-clamp-3 mb-3">
                ${review.body}
            </p>
            ${detailHtml}
            <div class="flex justify-between items-center text-[10px] text-gray-400 font-bold">
                <span class="flex items-center gap-1">
                    <i data-lucide="user" class="w-3 h-3 text-gray-300"></i> ${review.nickname || '匿名ユーザー'}
                </span>
                <span>${review.created_at}</span>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('afterbegin', html);
    if (window.lucide) window.lucide.createIcons();
}

document.addEventListener('DOMContentLoaded', () => {
    // 星の評価UIの制御
    var stars = document.querySelectorAll('.star-btn');
    var ratingInput = document.getElementById('rating-value');
    var ratingLabel = document.getElementById('rating-label');
    var labels = ['', '悪い', 'いまいち', '普通', '良い', '最高'];

    function paintStars(count) {
        stars.forEach(function(s, idx) {
            var svg = s.querySelector('svg');
            if (idx < count) {
                s.classList.add('text-yellow-400');
                s.classList.remove('text-gray-200');
                svg.classList.add('fill-current');
            } else {
                s.classList.remove('text-yellow-400');
                s.classList.add('text-gray-200');
                svg.classList.remove('fill-current');
            }
        });
    }

    if (stars.length > 0 && ratingInput) {
        stars.forEach(function(btn) {
            btn.addEventListener('click', function() {
                reviewRating = parseInt(btn.dataset.val);
                ratingInput.value = reviewRating;
                paintStars(reviewRating);
                if (ratingLabel) ratingLabel.textContent = labels[reviewRating] || '';
            });
            btn.addEventListener('mouseenter', function() {
                paintStars(parseInt(btn.dataset.val));
                if (ratingLabel) ratingLabel.textContent = labels[parseInt(btn.dataset.val)] || '';
            });
            btn.addEventListener('mouseleave', function() {
                paintStars(reviewRating);
                if (ratingLabel) ratingLabel.textContent = labels[reviewRating] || '';
            });
        });
    }

    // 項目別評価の星UI
    var detailStars = document.querySelectorAll('.detail-star-btn');
    var fieldValues = {};
    detailStars.forEach(function(btn) {
        var field = btn.dataset.field;
        if (!(field in fieldValues)) {
            var existing = document.getElementById(field + '-value');
            fieldValues[field] = existing ? (parseInt(existing.value) || 0) : 0;
        }
        function paintDetailStars(fieldName, count) {
            detailStars.forEach(function(s) {
                if (s.dataset.field !== fieldName) return;
                var svg = s.querySelector('svg');
                if (parseInt(s.dataset.val) <= count) {
                    s.classList.add('text-yellow-400');
                    s.classList.remove('text-gray-200');
                    svg.classList.add('fill-current');
                } else {
                    s.classList.remove('text-yellow-400');
                    s.classList.add('text-gray-200');
                    svg.classList.remove('fill-current');
                }
            });
        }
        btn.addEventListener('click', function() {
            var val = parseInt(btn.dataset.val);
            var fld = btn.dataset.field;
            // Toggle off if clicking same value
            if (fieldValues[fld] === val) {
                fieldValues[fld] = 0;
                document.getElementById(fld + '-value').value = '';
                paintDetailStars(fld, 0);
            } else {
                fieldValues[fld] = val;
                document.getElementById(fld + '-value').value = val;
                paintDetailStars(fld, val);
            }
        });
        btn.addEventListener('mouseenter', function() {
            paintDetailStars(btn.dataset.field, parseInt(btn.dataset.val));
        });
        btn.addEventListener('mouseleave', function() {
            paintDetailStars(btn.dataset.field, fieldValues[btn.dataset.field]);
        });
    });

    // レビューフォームのAjax送信
    const form = document.getElementById('review-form');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const btn = document.getElementById('review-submit-btn');
            const errorDiv = document.getElementById('review-error');
            const errorText = document.getElementById('review-error-text');
            const successMsg = document.getElementById('review-success-msg');
            
            const originalBtnText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> スパムチェック中...';
            if (window.lucide) window.lucide.createIcons();
            errorDiv.classList.add('hidden');
            
            try {
                // ★追加: reCAPTCHAトークンの取得
                const token = await new Promise((resolve, reject) => {
                    if (typeof grecaptcha !== 'undefined' && window.recaptchaSiteKey) {
                        grecaptcha.ready(() => {
                            grecaptcha.execute(window.recaptchaSiteKey, {action: 'submit_review'})
                                .then(resolve)
                                .catch(() => reject(new Error('スパム認証に失敗しました。')));
                        });
                    } else {
                        // 設定漏れ等の場合はとりあえず空で進める（バックエンドで弾かれます）
                        resolve(''); 
                    }
                });

                btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> 投稿しています...';

                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                data.recaptcha_token = token; // ★取得したトークンをデータに追加
                
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.content : '';
                
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(data)
                });
                
                if (!response.ok) {
                    const errData = await response.json();
                    let msg = errData.message || '通信エラーが発生しました。';
                    if (errData.errors) {
                        msg = Object.values(errData.errors).map(e => e.join('\n')).join('<br>');
                    }
                    throw new Error(msg);
                }
                
                const result = await response.json();
                
                // 送信成功処理
                form.classList.add('hidden');
                successMsg.classList.remove('hidden');
                appendReviewToList(result.review);
                
                setTimeout(() => {
                    closeReviewModal();
                    setTimeout(() => {
                        form.reset();
                        form.classList.remove('hidden');
                        successMsg.classList.add('hidden');
                        btn.disabled = false;
                        btn.innerHTML = originalBtnText;
                        // 星評価をデフォルト(5)にリセット
                        reviewRating = 5;
                        if (ratingInput) ratingInput.value = 5;
                        paintStars(5);
                        if (ratingLabel) ratingLabel.textContent = labels[5];
                        // 項目別評価をリセット
                        Object.keys(fieldValues).forEach(function(fld) {
                            fieldValues[fld] = 0;
                            var inp = document.getElementById(fld + '-value');
                            if (inp) inp.value = '';
                        });
                        detailStars.forEach(function(s) {
                            s.classList.remove('text-yellow-400');
                            s.classList.add('text-gray-200');
                            var svg = s.querySelector('svg');
                            if (svg) svg.classList.remove('fill-current');
                        });
                    }, 500);
                }, 2000);
                
            } catch (error) {
                errorText.innerHTML = error.message;
                errorDiv.classList.remove('hidden');
                btn.disabled = false;
                btn.innerHTML = originalBtnText;
                if (window.lucide) window.lucide.createIcons();
            }
        });
    }
});