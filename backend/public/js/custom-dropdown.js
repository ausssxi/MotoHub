/**
 * MotoHub Custom Dropdown Logic
 * 標準のselectを使わず、完全にカスタム可能なドロップダウンを制御します。
 */
document.addEventListener('DOMContentLoaded', () => {
    const dropdownBtn = document.getElementById('custom-sort-btn');
    const dropdownMenu = document.getElementById('custom-sort-menu');
    const dropdownLabel = document.getElementById('custom-sort-label');
    const filterForm = document.getElementById('filter-form');
    
    // フォーム内にある隠しソート入力欄（唯一のname="sort"である必要があります）
    const sortHiddenInput = document.getElementById('sort-hidden-input');

    if (!dropdownBtn || !dropdownMenu) return;

    // 1. メニューの開閉トグル
    const toggleMenu = (e) => {
        if (e) e.stopPropagation();
        const isHidden = dropdownMenu.classList.contains('hidden');
        
        if (isHidden) {
            dropdownMenu.classList.remove('hidden');
            dropdownBtn.classList.add('ring-2', 'ring-blue-500', 'border-blue-500');
        } else {
            dropdownMenu.classList.add('hidden');
            dropdownBtn.classList.remove('ring-2', 'ring-blue-500', 'border-blue-500');
        }
    };

    dropdownBtn.addEventListener('click', toggleMenu);

    // 2. 選択肢をクリックした時の処理
    dropdownMenu.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            const newValue = item.dataset.value;
            const newText = item.querySelector('span').textContent.trim();

            // デバッグ用：値が正しく取れているか確認（後で消してOK）
            console.log(`Sorting by: ${newValue}`);

            // A. ラベルのテキストを即座に更新
            if (dropdownLabel) dropdownLabel.textContent = newText;
            
            // B. フォームの隠しフィールドに値をセット
            if (sortHiddenInput) {
                sortHiddenInput.value = newValue;
            }

            // C. メニューを閉じる
            dropdownMenu.classList.add('hidden');
            dropdownBtn.classList.remove('ring-2', 'ring-blue-500', 'border-blue-500');

            // D. フォームを送信してリロード（これが「並び替え実行」の核心です）
            if (filterForm) {
                // 他のパラメータ（keyword等）を維持したまま送信されます
                filterForm.submit();
            }
        });
    });

    // 3. メニューの外側をクリックしたら閉じる
    document.addEventListener('click', (e) => {
        if (!dropdownBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.add('hidden');
            dropdownBtn.classList.remove('ring-2', 'ring-blue-500', 'border-blue-500');
        }
    });
});