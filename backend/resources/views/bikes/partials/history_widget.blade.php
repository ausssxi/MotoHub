{{-- 閲覧履歴ウィジェット（再利用可能パーシャル） --}}
@php $widgetId = $widgetId ?? 'history-widget'; @endphp

<section id="{{ $widgetId }}-section" class="hidden mb-12 bg-white rounded-3xl p-6 shadow-sm border border-gray-100 overflow-hidden min-w-0">
    <div class="flex items-center gap-2 mb-6">
        <div class="p-2 bg-gray-100 rounded-lg text-gray-600">
            <i data-lucide="clock" class="w-5 h-5"></i>
        </div>
        <h3 class="text-lg font-black text-gray-900">最近チェックした車両</h3>
    </div>

    <div id="{{ $widgetId }}" class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide">
    </div>
</section>

<script>
(function() {
    var wId = @json($widgetId);
    function tryRender() {
        if (typeof HistoryManager === 'undefined') {
            setTimeout(tryRender, 100);
            return;
        }
        var widget = document.getElementById(wId);
        if (!widget) return;

        var isLoggedIn = document.body.dataset.loggedIn === 'true';
        HistoryManager.init(isLoggedIn).then(function() {
            HistoryManager.render(wId).then(function() {
                if (widget.children.length > 0) {
                    document.getElementById(wId + '-section').classList.remove('hidden');
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryRender);
    } else {
        tryRender();
    }
})();
</script>
