/**
 * MotoHub Bike Models Page Logic
 * 車種一覧ページのアコーディオン開閉制御を行います。
 */

// メーカー（第1階層）の切り替え
function toggleMaker(id) {
    const list = document.getElementById('maker-list-' + id);
    const icon = document.getElementById('maker-icon-' + id);
    const section = document.getElementById('maker-section-' + id);
    
    if (list.classList.contains('hidden')) {
        // 開く
        list.classList.remove('hidden');
        list.classList.add('animate-in', 'fade-in', 'slide-in-from-top-2');
        icon.style.transform = 'rotate(180deg)';
        section.classList.add('ring-2', 'ring-blue-100');
    } else {
        // 閉じる
        list.classList.add('hidden');
        list.classList.remove('animate-in', 'fade-in', 'slide-in-from-top-2');
        icon.style.transform = 'rotate(0deg)';
        section.classList.remove('ring-2', 'ring-blue-100');
    }
}

// 50音グループ（第2階層）の切り替え
function toggleSubGroup(id) {
    const list = document.getElementById('sub-list-' + id);
    const icon = document.getElementById('sub-icon-' + id);
    
    if (list.classList.contains('hidden')) {
        // 開く
        list.classList.remove('hidden');
        list.classList.add('animate-in', 'fade-in', 'slide-in-from-top-1');
        icon.style.transform = 'rotate(180deg)';
    } else {
        // 閉じる
        list.classList.add('hidden');
        list.classList.remove('animate-in', 'fade-in', 'slide-in-from-top-1');
        icon.style.transform = 'rotate(0deg)';
    }
}