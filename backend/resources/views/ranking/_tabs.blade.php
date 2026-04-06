{{-- ランキング タブ切替 --}}
<div class="flex gap-1 mb-6 bg-gray-100 rounded-xl p-1">
    <a href="{{ route('ranking.daily') }}"
       class="flex-1 text-center py-2 px-3 rounded-lg text-xs font-black transition {{ $active === 'daily' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
        日別
    </a>
    <a href="{{ route('ranking.weekly') }}"
       class="flex-1 text-center py-2 px-3 rounded-lg text-xs font-black transition {{ $active === 'weekly' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
        週間
    </a>
    <a href="{{ route('ranking.index') }}"
       class="flex-1 text-center py-2 px-3 rounded-lg text-xs font-black transition {{ $active === 'monthly' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
        月間
    </a>
</div>
