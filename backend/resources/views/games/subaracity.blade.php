<x-layout>
    <x-slot:title>バイクガレージパズル | MotoHub</x-slot:title>
    <x-slot:metaDescription>同じメーカー色のブロックを合体させてバイクをレベルアップ！カブ50からゴールドウイングを目指すパズルゲーム。</x-slot:metaDescription>
    <x-slot:canonical>{{ url('/games/subaracity') }}</x-slot:canonical>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <x-slot:styles>
        <style>
            #subaracity-root { min-height: calc(100vh - 64px); }
        </style>
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "WebApplication",
            "name": "バイクガレージパズル",
            "description": "同じメーカー色のブロックを合体させてバイクをレベルアップ！カブ50からゴールドウイングを目指すパズルゲーム。",
            "url": "{{ url('/games/subaracity') }}",
            "applicationCategory": "Game",
            "operatingSystem": "Web Browser"
        }
        </script>
    </x-slot:styles>

    <div id="subaracity-root"></div>

    <x-slot:scripts>
        @vite(['resources/js/subaracity-app.jsx'])
    </x-slot:scripts>
</x-layout>
