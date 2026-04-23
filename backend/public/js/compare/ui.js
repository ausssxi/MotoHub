/**
 * MotoHub Compare UI Logic (Revised)
 * 比較ボタンの見た目制御と、下部のフローティングバーを管理します。
 */
document.addEventListener('DOMContentLoaded', () => {
    // 1. フローティングバーのHTML生成（変更なし）
    // スマホ対応のレスポンシブデザイン適用済み
    const barHtml = `
        <div id="compare-bar" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[100] transition duration-500 translate-y-24 opacity-0 pointer-events-none w-[92%] sm:w-auto">
            <div class="bg-gray-900/90 backdrop-blur-xl text-white px-4 py-3 md:px-6 md:py-4 rounded-full shadow-2xl border border-white/10 flex items-center justify-between sm:justify-start gap-3 md:gap-6 min-w-0 sm:min-w-[320px]">
                <div class="flex flex-col shrink-0">
                    <span class="hidden sm:block text-[10px] font-black text-blue-400 uppercase tracking-widest">Compare Mode</span>
                    <span class="text-xs sm:text-sm font-bold whitespace-nowrap"><span id="compare-count">0</span>台を選択中</span>
                </div>
                <div class="h-8 w-px bg-white/10 shrink-0"></div>
                <div class="flex items-center gap-2 md:gap-3 shrink-0">
                    <button id="compare-clear-btn" class="text-[10px] sm:text-xs font-bold text-gray-400 hover:text-white transition-colors whitespace-nowrap">クリア</button>
                    <a href="/compare" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 md:px-6 md:py-2.5 rounded-full text-xs sm:text-sm font-black transition flex items-center gap-1.5 md:gap-2 group shadow-lg shadow-blue-600/20 whitespace-nowrap">
                        比較する
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5 sm:w-4 sm:h-4 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                </div>
            </div>
        </div>
    `;
    
    // 既にバーが存在しない場合のみ追加
    if (!document.getElementById('compare-bar')) {
        document.body.insertAdjacentHTML('beforeend', barHtml);
    }

    const compareBar = document.getElementById('compare-bar');
    const compareCount = document.getElementById('compare-count');
    const clearBtn = document.getElementById('compare-clear-btn');

    // 2. UI更新処理
    const refreshCompareUI = () => {
        if (typeof Compare === 'undefined') return;

        const ids = Compare.getIds();
        const count = ids.length;

        // クラス名を .compare-btn に統一して取得
        document.querySelectorAll('.compare-btn').forEach(btn => {
            const id = parseInt(btn.dataset.id);
            const iconBtn = btn.querySelector('.compare-icon');
            const label = btn.querySelector('.compare-label');
            const sub = btn.querySelector('.compare-sub');
            const isCompact = !iconBtn; // カード上のコンパクトボタン

            if (ids.includes(id)) {
                if (isCompact) {
                    // --- コンパクトボタン（検索カード等）: 選択中 ---
                    btn.classList.remove('bg-white/90', 'text-gray-400', 'border-gray-100', 'border');
                    btn.classList.add('bg-blue-500', 'text-white', 'border-2', 'border-blue-500', 'ring-2', 'ring-blue-300');
                    btn.innerHTML = '<i data-lucide="check" class="w-5 h-5"></i>';
                } else {
                    // --- リッチボタン（詳細ページ）: 選択中 ---
                    btn.classList.add('bg-blue-50', 'border-blue-200');
                    btn.classList.remove('bg-gray-50', 'border-gray-200');
                    iconBtn.classList.add('border-blue-300', 'bg-blue-50', 'text-blue-600');
                    iconBtn.classList.remove('border-gray-200', 'bg-white', 'text-gray-400');
                    iconBtn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i>';
                    if (label) { label.textContent = '比較中'; label.classList.add('text-blue-700'); label.classList.remove('text-gray-900'); }
                    if (sub) { sub.textContent = 'タップで解除'; sub.classList.add('text-blue-500'); sub.classList.remove('text-gray-500'); }
                }
            } else {
                if (isCompact) {
                    // --- コンパクトボタン（検索カード等）: 未選択 ---
                    btn.classList.remove('bg-blue-500', 'text-white', 'border-2', 'border-blue-500', 'ring-2', 'ring-blue-300');
                    btn.classList.add('bg-white/90', 'text-gray-400', 'border', 'border-gray-100');
                    btn.innerHTML = '<i data-lucide="layers" class="w-5 h-5"></i>';
                } else {
                    // --- リッチボタン（詳細ページ）: 未選択 ---
                    btn.classList.remove('bg-blue-50', 'border-blue-200');
                    btn.classList.add('bg-gray-50', 'border-gray-200');
                    iconBtn.classList.remove('border-blue-300', 'bg-blue-50', 'text-blue-600');
                    iconBtn.classList.add('border-gray-200', 'bg-white', 'text-gray-400');
                    iconBtn.innerHTML = '<i data-lucide="layers" class="w-4 h-4"></i>';
                    if (label) { label.textContent = '比較'; label.classList.remove('text-blue-700'); label.classList.add('text-gray-900'); }
                    if (sub) { sub.textContent = 'リストに追加'; sub.classList.remove('text-blue-500'); sub.classList.add('text-gray-500'); }
                }
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

    // 3. クリックイベント
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.compare-btn');
        if (btn) {
            e.preventDefault(); // リンク遷移などを防止
            const id = btn.dataset.id;
            if (typeof Compare !== 'undefined') {
                const result = Compare.toggle(id);
                if (!result.success) {
                    alert(result.message);
                }
                refreshCompareUI();
            }
        }
    });

    // 4. クリアボタン
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