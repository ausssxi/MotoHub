<x-layout>
    <x-slot:title>バイク2048パズル | MotoHub</x-slot:title>
    <x-slot:metaDescription>カブ50からスタート！同じバイクを合わせてグレードアップ。ゴールドウイングを目指す2048パズルゲーム。</x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <x-slot:styles>
        <style>
            #bike-puzzle-root { min-height: calc(100vh - 64px); }
        </style>
    </x-slot:styles>

    <div id="bike-puzzle-root"></div>

    <x-slot:scripts>
        @vite(['resources/js/puzzle-app.jsx'])
    </x-slot:scripts>
</x-layout>
