import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        // vendor のビュー（ページネーション）。Laravel 既定の tailwind ページャを使っており
        // （bootstrap 上書きなし・各 ->links() は既定ビュー）、ここが無いとページャの
        // flex / space-x / rounded などが抜ける。simple 版も含めて明示登録する。
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/tailwind.blade.php',
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/simple-tailwind.blade.php',
        // ★ storage/framework/views（コンパイル済み Blade キャッシュ）は content に入れない。
        //   デプロイ時にキャッシュが温まっている/消えているでビルド対象が変わり、生成 CSS が
        //   142KB↔155KB と揺れていた（温かいと古い blade 版の死にクラスまで混ざる）。
        //   コンパイル済みビューは下の resources/views/**/*.blade.php が元ソースなので、
        //   source を走査すれば同じ（かそれ以上の）クラスを決定的に拾える。実測で
        //   storage 有無のビルドはバイト一致（134,442B）を確認済み。
        './resources/views/**/*.blade.php',
        './public/js/**/*.js',    // ★ 追加：publicディレクトリのJSファイル
        './resources/js/**/*.js', // ★ 追加：resourcesディレクトリのJSファイル（念のため）
        './app/Services/**/*.php', // ★追加: サービス内のクラスも抽出する
        './config/bike.php',
    ],

    safelist: [
        'z-[9999]', 
        'z-[9998]',

        // LINEカラー (以前追加したもの)
        'bg-line-green',
        'bg-line-green-hover',
        
        // グラデーション関連
        // Tailwindは from-xxx to-xxx を個別のクラスとして扱うため、1つずつ記述します
        'from-blue-500',
        'from-blue-600',
        'to-indigo-600',
        'to-indigo-700',

        // パーツ比較ページ (JS template literal内で使用)
        'bg-amber-500',
        'bg-amber-600',
        'hover:bg-amber-600',
        'bg-blue-500',
        'bg-blue-600',
        'hover:bg-blue-600',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'line-green': '#06C755',
                'line-green-hover': '#05b34c',
            },
        },
    },

    plugins: [forms],
};
