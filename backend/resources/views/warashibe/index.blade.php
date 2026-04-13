<x-layout>
    <x-slot:title>バイクわらしべ長者 - カブ50から始まる交換の旅 | MotoHub</x-slot:title>
    <x-slot:metaDescription>カブ50から始まる、交換の旅。バイクを交換しながらドリームバイクを目指すブラウザゲーム。</x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <x-slot:styles>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700;900&display=swap" rel="stylesheet">
        <style>
            #warashibe-root { min-height: calc(100vh - 64px); }
        </style>
    </x-slot:styles>

    <div id="warashibe-root"></div>

    <x-slot:scripts>
        @vite(['resources/js/warashibe-app.jsx'])
    </x-slot:scripts>
</x-layout>
