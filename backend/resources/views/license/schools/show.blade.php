<x-layout>
    <x-slot:title>{{ $prefecture }}で二輪免許が取れる指定自動車教習所一覧｜MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}で普通二輪・大型二輪の教習を行っている指定自動車教習所の一覧です。市区町村・対応免許区分・公式サイトへのリンクをまとめています。</x-slot:metaDescription>
    <x-slot:canonical>https://motohub.jp/license/schools/{{ $pref }}</x-slot:canonical>
    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">

        {{-- ヒーロー --}}
        <div class="bg-gradient-to-br from-slate-900 to-blue-900 text-white pt-8 pb-10 px-4">
            <div class="max-w-3xl mx-auto">
                <nav class="text-xs text-blue-300 font-bold mb-4">
                    <a href="{{ route('license.schools.index') }}" class="hover:underline">二輪免許が取れる指定自動車教習所</a>
                    <span class="mx-1.5 text-blue-500">/</span>
                    <span class="text-blue-100">{{ $prefecture }}</span>
                </nav>
                <div class="text-4xl mb-2">🏍️</div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight">{{ $prefecture }}で二輪免許が取れる指定自動車教習所</h1>
            </div>
        </div>

        <div class="max-w-3xl mx-auto px-4 py-10 space-y-10">

            {{-- 受付状況の注意書き（ページに一度だけ） --}}
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-[13px] text-amber-900 leading-relaxed">
                受付状況は変わることがあります。定員や休講により、掲載時点と異なる場合があります。お申し込み前に各校の公式サイトでご確認ください。
            </div>

            {{-- 一覧 --}}
            <section>
                <div class="bg-white rounded-2xl border border-slate-200 overflow-x-auto">
                    <table class="w-full text-sm min-w-[560px]">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-black">市区町村</th>
                                <th class="px-4 py-3 font-black">教習所名</th>
                                <th class="px-4 py-3 font-black text-center">普通二輪</th>
                                <th class="px-4 py-3 font-black text-center">大型二輪</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($schools as $s)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-600 font-bold whitespace-nowrap">{{ $s->city }}</td>
                                    <td class="px-4 py-3">
                                        @if($s->official_url)
                                            <a href="{{ $s->official_url }}" target="_blank" rel="nofollow noopener"
                                               class="font-bold text-blue-700 hover:underline">{{ $s->name }}</a>
                                        @else
                                            <span class="font-bold text-slate-900">{{ $s->name }}</span>
                                        @endif
                                        <div class="text-[11px] text-slate-400 mt-1">{{ $s->verified_at->format('Y') }}年{{ $s->verified_at->format('n') }}月時点で公式サイトに二輪教習の案内を確認</div>
                                        @if($s->isStale())
                                            <div class="text-[11px] text-amber-600 mt-0.5">この情報は確認から時間が経っています</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center {{ $s->futsuu_nirin ? 'font-black text-emerald-600' : 'text-slate-300' }}">
                                        {{ $s->futsuu_nirin ? '○' : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center {{ $s->oogata_nirin ? 'font-black text-emerald-600' : 'text-slate-300' }}">
                                        {{ $s->oogata_nirin ? '○' : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- 免許区分への導線 --}}
            <section>
                <div class="grid sm:grid-cols-2 gap-3">
                    <a href="/license/futsuu"
                       class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
                        <div class="text-sm font-black text-slate-900">🏍️ 普通二輪でどんなバイクに乗れるか見る</div>
                    </a>
                    <a href="/license/oogata"
                       class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
                        <div class="text-sm font-black text-slate-900">🏁 大型二輪でどんなバイクに乗れるか見る</div>
                    </a>
                </div>
            </section>

            {{-- 免責文・出典 --}}
            <section class="border-t border-slate-200 pt-6">
                <p class="text-[11px] text-slate-400 leading-relaxed">
                    この一覧は各都道府県の指定自動車教習所協会が公表している会員校リストをもとに作成しています。協会に加盟していない教習所や、掲載後に取扱いが変わった教習所は反映されていない場合があります。教習料金・入校条件・二輪教習の実施状況は各教習所が個別に定めているため、お申し込み前に必ず各校の公式サイトでご確認ください。
                </p>
                <p class="text-[11px] text-slate-400 mt-3 leading-relaxed">
                    @foreach($sourceUrls as $url)
                        出典：<a href="{{ $url }}" target="_blank" rel="nofollow noopener" class="text-blue-600 hover:underline">{{ $url }}</a>@if(!$loop->last)<br>@endif
                    @endforeach
                </p>
            </section>

        </div>
    </div>
</x-layout>
