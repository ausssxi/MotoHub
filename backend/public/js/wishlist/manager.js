/**
 * MotoHub Wishlist Logic (LocalStorage implementation)
 */
const WISHLIST_KEY = 'motohub_wishlist';

const Wishlist = {
    // 全てのIDを取得
    getIds() {
        const stored = localStorage.getItem(WISHLIST_KEY);
        return stored ? JSON.parse(stored) : [];
    },

    // IDを追加または削除
    toggle(id) {
        let ids = this.getIds();
        const index = ids.indexOf(id);
        
        if (index === -1) {
            ids.push(id);
            this.notify('お気に入りに追加しました');
        } else {
            ids.splice(index, 1);
            this.notify('お気に入りから削除しました');
        }
        
        localStorage.setItem(WISHLIST_KEY, JSON.stringify(ids));
        this.updateUI();
        return index === -1; // 追加された場合はtrue
    },

    // 特定のIDが含まれているか確認
    has(id) {
        return this.getIds().includes(id);
    },

    // UIの更新（カウンターとボタンの状態）
    updateUI() {
        const ids = this.getIds();
        
        // ナビゲーションのカウンター更新
        const counter = document.getElementById('wishlist-count');
        if (counter) {
            counter.textContent = ids.length;
            counter.parentElement.classList.toggle('hidden', ids.length === 0);
        }

        // 各ボタンのアクティブ状態を反映
        document.querySelectorAll('.wishlist-btn').forEach(btn => {
            const id = parseInt(btn.dataset.id);
            const icon = btn.querySelector('i');
            if (this.has(id)) {
                btn.classList.add('active', 'text-red-500');
                btn.classList.remove('text-gray-400');
                if (icon) icon.setAttribute('data-lucide', 'heart-handshake'); // または 'heart'
                btn.style.fill = 'currentColor';
            } else {
                btn.classList.remove('active', 'text-red-500');
                btn.classList.add('text-gray-400');
                btn.style.fill = 'none';
            }
        });

        if (window.lucide) window.lucide.createIcons();
    },

    // 簡易通知ツール
    notify(message) {
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-8 left-1/2 -translate-x-1/2 bg-black/90 text-white px-6 py-3 rounded-full text-xs font-black z-[100] shadow-2xl animate-in fade-in slide-in-from-bottom-4 duration-300';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('animate-out', 'fade-out', 'slide-out-to-bottom-4');
            setTimeout(() => toast.remove(), 300);
        }, 2000);
    }
};

// 初期化
document.addEventListener('DOMContentLoaded', () => {
    Wishlist.updateUI();

    // ボタンクリックイベントの委譲
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.wishlist-btn');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            const id = parseInt(btn.dataset.id);
            Wishlist.toggle(id);
        }
    });
});