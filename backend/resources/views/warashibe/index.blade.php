<x-layout>
    <x-slot:title>バイクわらしべ長者 - カブ50から始まる交換の旅 | MotoHub</x-slot:title>
    <x-slot:metaDescription>蕎麦屋のゲンさんからカブ50をもらい、21人のライダーとバイクを交換してハヤブサを目指す交換パズルゲーム</x-slot:metaDescription>
    <x-slot:ogImage>https://motohub.jp/images/warashibe/ogp.png</x-slot:ogImage>
    <x-slot:canonical>https://motohub.jp/warashibe</x-slot:canonical>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <x-slot:styles>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700;900&display=swap" rel="stylesheet">
        <style>
            #warashibe-root { min-height: calc(100vh - 64px); }
        </style>
    </x-slot:styles>

    <h1 class="sr-only">バイクわらしべ長者ゲーム｜カブ50からハヤブサを目指す交換パズル</h1>
    <div id="warashibe-root"></div>

    <x-slot:scripts>
        @vite(['resources/js/warashibe-app.jsx'])
    </x-slot:scripts>
</x-layout>
