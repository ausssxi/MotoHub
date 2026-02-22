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
    const stars = document.querySelectorAll('.star-btn');
    const ratingInput = document.getElementById('rating-value');
    
    if (stars.length > 0 && ratingInput) {
        stars.forEach(btn => {
            btn.addEventListener('click', () => {
                reviewRating = parseInt(btn.dataset.val);
                ratingInput.value = reviewRating;
                
                stars.forEach((s, idx) => {
                    const icon = s.querySelector('i');
                    if (idx < reviewRating) {
                        icon.classList.add('fill-current');
                        s.classList.add('text-yellow-400');
                        s.classList.remove('text-gray-300');
                    } else {
                        icon.classList.remove('fill-current');
                        s.classList.remove('text-yellow-400');
                        s.classList.add('text-gray-300');
                    }
                });
            });
        });
    }

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
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> 投稿しています...';
            if (window.lucide) window.lucide.createIcons();
            errorDiv.classList.add('hidden');
            
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const token = csrfMeta ? csrfMeta.content : '';
                
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
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
                        if(stars[4]) stars[4].click();
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