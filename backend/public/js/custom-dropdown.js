/**
 * MotoHub Custom Dropdown Logic
 * 既にURLにあるパラメータを汚さず、ソート順だけをスマートに切り替えます。
 */
document.addEventListener('DOMContentLoaded', () => {
    const dropdownBtn = document.getElementById('custom-sort-btn');
    const dropdownMenu = document.getElementById('custom-sort-menu');

    if (!dropdownBtn || !dropdownMenu) return;

    const toggleMenu = (e) => {
        if (e) e.stopPropagation();
        dropdownMenu.classList.toggle('hidden');
    };

    dropdownBtn.addEventListener('click', toggleMenu);

    dropdownMenu.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            const newValue = item.dataset.value;
            
            // 現在のURLパラメータを取得
            const urlParams = new URLSearchParams(window.location.search);
            
            // 1. ソート順を更新
            urlParams.set('sort', newValue);
            
            // 2. ✨ ソート順が変わる時は、ページ番号を1に戻す（デグレーション防止）
            urlParams.delete('page');
            
            // 3. ✨ 空のパラメータや不要なものを徹底掃除
            const keys = Array.from(urlParams.keys());
            keys.forEach(key => {
                const val = urlParams.get(key);
                if (!val || val === "null" || val === "" || val === "undefined") {
                    urlParams.delete(key);
                }
            });

            // 新しいURLへ遷移
            window.location.href = `${window.location.pathname}?${urlParams.toString()}`;
        });
    });

    document.addEventListener('click', (e) => {
        if (!dropdownBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.add('hidden');
        }
    });
});