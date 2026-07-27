<x-layout>
    <x-slot:title>二輪免許が取れる指定自動車教習所一覧｜MotoHub</x-slot:title>
    <x-slot:metaDescription>普通二輪・大型二輪の教習を行っている指定自動車教習所を都道府県別にまとめました。出典は各都道府県の指定自動車教習所協会の公表リストです。</x-slot:metaDescription>
    <x-slot:canonical>https://motohub.jp/license/schools</x-slot:canonical>
    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">

        {{-- ヒーロー --}}
        <div class="bg-gradient-to-br from-slate-900 to-blue-900 text-white pt-10 pb-12 px-4">
            <div class="max-w-3xl mx-auto text-center">
                <div class="text-4xl mb-3">🏍️</div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight mb-2">二輪免許が取れる指定自動車教習所</h1>
                <p class="text-blue-200 text-sm font-bold">
                    普通二輪・大型二輪の教習を行っている指定自動車教習所を、都道府県別にまとめています。
                </p>
            </div>
        </div>

        <div class="max-w-3xl mx-auto px-4 py-10 space-y-10">

            {{-- リード文 --}}
            <section>
                <p class="text-sm text-slate-700 leading-relaxed">
                    普通二輪・大型二輪の教習を行っている指定自動車教習所を、都道府県別にまとめています。免許区分ごとに乗れるバイクと中古相場を知りたい方は、<a href="/license" class="text-blue-700 font-bold hover:underline">バイク免許ガイド</a>もあわせてご覧ください。
                </p>
            </section>

            {{-- 都道府県カード --}}
            <section>
                @if($prefectures->isEmpty())
                    <p class="text-sm text-slate-500">現在、公開できる教習所情報がありません。</p>
                @else
                    <div class="grid sm:grid-cols-2 gap-3">
                        @foreach($prefectures as $p)
                            <a href="{{ route('license.schools.show', $p->prefecture_slug) }}"
                               class="block bg-white rounded-2xl border border-slate-200 p-5 hover:border-blue-400 hover:shadow-lg transition">
                                <div class="flex items-center justify-between">
                                    <span class="font-black text-slate-900 text-lg">{{ $p->prefecture }}</span>
                                    <span class="text-sm font-black text-blue-700 tabular-nums">{{ number_format($p->count) }}校</span>
                                </div>
                                @if($p->last_verified)
                                    @php($lv = \Illuminate\Support\Carbon::parse($p->last_verified))
                                    <div class="text-[11px] text-slate-400 mt-1">最終確認: {{ $lv->format('Y') }}年{{ $lv->format('n') }}月</div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- 免責文 --}}
            <section class="border-t border-slate-200 pt-6">
                <p class="text-[11px] text-slate-400 leading-relaxed">
                    この一覧は各都道府県の指定自動車教習所協会が公表している会員校リストをもとに作成しています。協会に加盟していない教習所や、掲載後に取扱いが変わった教習所は反映されていない場合があります。教習料金・入校条件・二輪教習の実施状況は各教習所が個別に定めているため、お申し込み前に必ず各校の公式サイトでご確認ください。
                </p>
            </section>

        </div>
    </div>
</x-layout>
