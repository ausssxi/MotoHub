{{--
    公開ガレージのいいねボタン（NewsComment いいねと同じ inline-Alpine + fetch・@json不使用＝リテラル補間のみ）。
    引数: $bikeId, $likeCount, $liked(bool・既いいね), $isOwner(bool・自分のガレージ)
    ※自分のガレージ/未ログインはボタンを出さず数のみ表示（サーバ側 422/auth と二重防御）。
    ※ハートは inline SVG（lucide置換でAlpineバインドが外れるのを避け、:fill で liked 状態を反映）。
--}}
@php
    $liked = $liked ?? false;
    $isOwner = $isOwner ?? false;
    $heartPath = 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z';
@endphp
<div x-data="{ liked: {{ $liked ? 'true' : 'false' }}, count: {{ (int) $likeCount }}, loading: false }" class="inline-flex items-center">
    @auth
        @if($isOwner)
            <span class="inline-flex items-center gap-1 text-xs font-bold text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $heartPath }}"/></svg>
                <span x-text="count">{{ (int) $likeCount }}</span>
            </span>
        @else
            <button type="button"
                    @click="if(!loading){ loading=true; fetch('{{ route('garage.like', $bikeId) }}', { method:'POST', headers:{ 'X-CSRF-TOKEN':'{{ csrf_token() }}', 'Accept':'application/json' } }).then(r => { if(r.status===401||r.status===419){ window.location='{{ route('login') }}'; return null; } return r.json(); }).then(d => { if(d){ liked=d.liked; count=d.like_count; } loading=false; }).catch(() => { loading=false; }) }"
                    :class="liked ? 'text-red-500' : 'text-gray-400 hover:text-red-400'"
                    class="inline-flex items-center gap-1 text-xs font-bold transition-colors"
                    :aria-pressed="liked" aria-label="いいね">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" :fill="liked ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $heartPath }}"/></svg>
                <span x-text="count">{{ (int) $likeCount }}</span>
            </button>
        @endif
    @else
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1 text-xs font-bold text-gray-400 hover:text-red-400 transition-colors" aria-label="いいねするにはログイン">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $heartPath }}"/></svg>
            <span>{{ (int) $likeCount }}</span>
        </a>
    @endauth
</div>
