/**
 * MotoHub Compare UI Logic (Revised)
 */
document.addEventListener('DOMContentLoaded', () => {
    // 1. フローティングバーのHTML生成（変更なし）
    const barHtml = `
        <div id="compare-bar" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[100] transition-all duration-500 translate-y-24 opacity-0 pointer-events-none w-[92%] sm:w-auto">
            <div class="bg-gray-900/90 backdrop-blur-xl text-white px-4 py-3 md:px-6 md:py-4 rounded-full shadow-2xl border border-white/10 flex items-center justify-between sm:justify-start gap-3 md:gap-6 min-w-0 sm:min-w-[320px]">
            
                <div class="flex flex-col shrink-0">
                    <span class="hidden sm:block text-[10px] font-black text-blue-400 tracking-widest">Compare Mode</span>
                    <span class="text-xs sm:text-sm font-bold whitespace-nowrap"><span id="compare-count">0</span>台を選択中</span>
                </div>
            
                <div class="h-8 w-px bg-white/10 shrink-0"></div>
            
                <div class="flex items-center gap-2 md:gap-3 shrink-0">
                    <button id="compare-clear-btn" class="text-[10px] sm:text-xs font-bold text-gray-400 hover:text-white transition-colors whitespace-nowrap">クリア</button>
                    <a href="/compare" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 md:px-6 md:py-2.5 rounded-full text-xs sm:text-sm font-black transition-all flex items-center gap-1.5 md:gap-2 group shadow-lg shadow-blue-600/20 whitespace-nowrap">
                        比較する
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5 sm:w-4 sm:h-4 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', barHtml);

    const compareBar = document.getElementById('compare-bar');
    const compareCount = document.getElementById('compare-count');
    const clearBtn = document.getElementById('compare-clear-btn');

    // 2. UI更新処理（チェックマークに色をつける修正）
    const refreshCompareUI = () => {
        if (typeof Compare === 'undefined') return;

        const ids = Compare.getIds();
        const count = ids.length;

        document.querySelectorAll('.compare-btn').forEach(btn => {
            const id = parseInt(btn.dataset.id);
            // 既存のアイコン要素を取得（ちらつき防止のため）
            const icon = btn.querySelector('i') || btn.querySelector('svg'); 
            
            if (ids.includes(id)) {
                // --- 選択中の状態（青背景） ---
                btn.classList.add('bg-blue-600', 'border-blue-600');
                btn.classList.remove('bg-white', 'text-gray-400', 'border-gray-200');
                
                // ここでボタンの基本文字色を白に設定
                btn.classList.add('text-white');

                if(icon) {
                    icon.setAttribute('data-lucide', 'check');
                    icon.classList.remove('text-gray-400'); // 念のため削除
                    icon.classList.add('text-blue-600');  // ★ここで色付け
                    btn.innerHTML = '<i data-lucide="check" class="w-5 h-5 text-blue-600"></i>'; 
                }
            } else {
                btn.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
                btn.classList.add('bg-white', 'text-gray-400', 'border-gray-200');
                btn.innerHTML = '<i data-lucide="layers" class="w-5 h-5"></i>';
            }
        });

        // バーの表示制御
        if (count > 0) {
            compareBar.classList.remove('translate-y-24', 'opacity-0', 'pointer-events-none');
            compareCount.textContent = count;
        } else {
            compareBar.classList.add('translate-y-24', 'opacity-0', 'pointer-events-none');
        }

        if (window.lucide) window.lucide.createIcons();
    };

    // 3. クリックイベント（変更なし）
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.compare-btn');
        if (btn) {
            const id = btn.dataset.id;
            // Compareオブジェクトが存在するか確認
            if (typeof Compare !== 'undefined') {
                const result = Compare.toggle(id);
                if (!result.success) {
                    alert(result.message);
                }
                refreshCompareUI();
            } else {
                console.error('Compare logic is missing.');
            }
        }
    });

    // 4. クリアボタン（変更なし）
    clearBtn?.addEventListener('click', () => {
        if (typeof Compare !== 'undefined') {
            Compare.clear();
            refreshCompareUI();
        }
    });

    // 初期化
    refreshCompareUI();
    document.addEventListener('compare-changed', refreshCompareUI);
});