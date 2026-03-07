/**
 * MotoHub Bike Models Page Logic
 * メーカーアコーディオン開閉 + Ajax遅延読み込み
 */

var _makerLoaded = {};

// メーカー（第1階層）の切り替え
function toggleMaker(id) {
    var list = document.getElementById('maker-list-' + id);
    var icon = document.getElementById('maker-icon-' + id);
    var section = document.getElementById('maker-section-' + id);

    if (list.classList.contains('hidden')) {
        list.classList.remove('hidden');
        list.classList.add('animate-in', 'fade-in', 'slide-in-from-top-2');
        icon.style.transform = 'rotate(180deg)';
        section.classList.add('ring-2', 'ring-blue-100');

        // 初回のみAjaxで車種データを取得
        if (!_makerLoaded[id]) {
            _makerLoaded[id] = true;
            loadMakerModels(id);
        }
    } else {
        list.classList.add('hidden');
        list.classList.remove('animate-in', 'fade-in', 'slide-in-from-top-2');
        icon.style.transform = 'rotate(0deg)';
        section.classList.remove('ring-2', 'ring-blue-100');
    }
}

// Ajax: メーカー別車種を取得してレンダリング
function loadMakerModels(makerId) {
    var container = document.getElementById('maker-content-' + makerId);

    fetch('/bikes/models/' + makerId + '/bikes')
        .then(function (res) { return res.json(); })
        .then(function (data) {
            container.innerHTML = renderGroups(data.groups, makerId);
            if (typeof lucide !== 'undefined') lucide.createIcons();
        })
        .catch(function () {
            container.innerHTML = '<p class="text-center text-red-500 text-xs font-bold py-4">読み込みに失敗しました。再度お試しください。</p>';
            _makerLoaded[makerId] = false;
        });
}

// グループ（A-Z, あ行等）をHTMLにレンダリング
function renderGroups(groups, makerId) {
    var html = '';
    var idx = 0;
    for (var label in groups) {
        var list = groups[label];
        if (!list || list.length === 0) continue;
        var subId = makerId + '-' + idx;
        html += '<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">'
            + '<button onclick="toggleSubGroup(\'' + subId + '\')" class="w-full flex items-center justify-between px-4 py-3 bg-white hover:bg-gray-50 transition-colors text-left group/sub">'
            + '<div class="flex items-center gap-3">'
            + '<span class="text-sm font-bold text-gray-700 min-w-[2rem]">' + escHtml(label) + '</span>'
            + '<span class="text-[10px] font-medium text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">' + list.length + '</span>'
            + '</div>'
            + '<i data-lucide="chevron-down" class="w-4 h-4 text-gray-300 group-hover/sub:text-blue-500 transition-transform duration-200" id="sub-icon-' + subId + '"></i>'
            + '</button>'
            + '<div id="sub-list-' + subId + '" class="hidden border-t border-gray-100 p-3 sm:p-4 bg-gray-50/50">'
            + '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">'
            + renderBikeCards(list)
            + '</div></div></div>';
        idx++;
    }
    return html;
}

// 車種カード一覧をHTMLにレンダリング
function renderBikeCards(bikes) {
    var html = '';
    for (var i = 0; i < bikes.length; i++) {
        var b = bikes[i];
        var imgHtml = b.image_url
            ? '<img src="' + escAttr(b.image_url) + '" alt="' + escAttr(b.name) + '" loading="lazy" decoding="async" class="w-full h-full object-cover transform group-hover/item:scale-110 transition-transform duration-500" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">'
              + '<div class="hidden w-full h-full items-center justify-center text-gray-300"><i data-lucide="bike" class="w-5 h-5"></i></div>'
            : '<div class="w-full h-full flex items-center justify-center text-gray-300"><i data-lucide="bike" class="w-5 h-5"></i></div>';

        var reviewHtml;
        if (b.reviews_avg_rating && b.reviews_count > 0) {
            reviewHtml = '<i data-lucide="star" class="w-4 h-4 sm:w-3 sm:h-3 fill-current text-yellow-500"></i>'
                + '<span class="text-[8px] sm:text-[10px] font-bold leading-none">' + Number(b.reviews_avg_rating).toFixed(1) + '<span class="hidden sm:inline"> (' + b.reviews_count + ')</span></span>';
        } else {
            reviewHtml = '<i data-lucide="message-square-quote" class="w-4 h-4 sm:w-3 sm:h-3"></i><span class="text-[8px] sm:text-[10px] font-bold leading-none">口コミ</span>';
        }

        var searchUrl = '/bikes/search?bike_model_id=' + b.id;
        var reviewUrl = (b.seo_url || '#') + '#reviews';

        html += '<div class="group/item relative flex items-center p-3 bg-white rounded-xl border border-gray-200 hover:border-blue-400 hover:shadow-md hover:-translate-y-0.5 transition duration-300">'
            + '<a href="' + escAttr(searchUrl) + '" class="absolute inset-0 z-10"></a>'
            + '<div class="w-12 h-12 sm:w-14 sm:h-14 rounded-lg bg-gray-50 overflow-hidden flex-shrink-0 mr-3 border border-gray-100 relative">' + imgHtml + '</div>'
            + '<div class="flex-1 min-w-0 pr-2 pointer-events-none relative z-0">'
            + '<h3 class="text-xs font-bold text-gray-700 leading-tight group-hover/item:text-blue-600 transition-colors line-clamp-1 mb-1">' + escHtml(b.name) + '</h3>'
            + '<span class="inline-flex items-center text-[9px] font-medium text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-100">'
            + '<span class="text-blue-500 font-bold mr-0.5">' + (b.listings_count || 0).toLocaleString() + '</span>台</span></div>'
            + '<a href="' + escAttr(reviewUrl) + '" onclick="event.stopPropagation();" class="relative z-20 shrink-0 inline-flex items-center justify-center w-11 h-11 sm:w-auto sm:h-auto sm:px-3 sm:py-2 bg-yellow-50 hover:bg-yellow-100 text-yellow-600 rounded-lg border border-yellow-200 transition-colors shadow-sm group-hover/item:border-yellow-300 flex-col sm:flex-row gap-0.5 sm:gap-1.5" title="オーナーの口コミを見る">'
            + reviewHtml + '</a></div>';
    }
    return html;
}

// 50音グループ（第2階層）の切り替え
function toggleSubGroup(id) {
    var list = document.getElementById('sub-list-' + id);
    var icon = document.getElementById('sub-icon-' + id);

    if (list.classList.contains('hidden')) {
        list.classList.remove('hidden');
        list.classList.add('animate-in', 'fade-in', 'slide-in-from-top-1');
        icon.style.transform = 'rotate(180deg)';
    } else {
        list.classList.add('hidden');
        list.classList.remove('animate-in', 'fade-in', 'slide-in-from-top-1');
        icon.style.transform = 'rotate(0deg)';
    }
}

// ユーティリティ
function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}
function escAttr(s) {
    return (s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
