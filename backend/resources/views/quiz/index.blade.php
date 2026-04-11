<x-layout>
    <x-slot:title>バイク4択クイズ - 中古価格・売れ筋・売却スピードを当てろ！ | MotoHub</x-slot:title>
    <x-slot:metaDescription>MotoHubの実データを使ったバイク4択クイズ！中古価格、売れ筋ランキング、売却スピード、地域別人気をクイズで学ぼう。</x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <x-slot:styles>
        <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@400;700;800;900&display=swap" rel="stylesheet">
        <style>
            #bike-quiz-root { min-height: calc(100vh - 64px); }
        </style>
    </x-slot:styles>

    <div id="bike-quiz-root"></div>

    <x-slot:scripts>
        @vite(['resources/js/quiz-app.jsx'])
    </x-slot:scripts>
</x-layout>
