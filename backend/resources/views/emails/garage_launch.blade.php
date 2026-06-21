<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    {{-- メーラー対応のため Tailwind ではなくインラインCSS風（price_drop 等と同流儀） --}}
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #f9fafb; color: #111827; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .header { background-color: #db2777; color: #ffffff; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 900; }
        .content { padding: 32px; line-height: 1.8; font-size: 14px; }
        .lead { margin: 0 0 20px; }
        .feature { background-color: #fdf2f8; border: 1px solid #fbcfe8; border-radius: 8px; padding: 16px 20px; margin-bottom: 16px; }
        .feature h2 { margin: 0 0 6px; font-size: 15px; font-weight: 900; color: #be185d; }
        .feature p { margin: 0; font-size: 13px; color: #374151; }
        .button-container { text-align: center; margin: 28px 0; }
        .button { display: inline-block; background-color: #db2777; color: #ffffff; padding: 14px 36px; text-decoration: none; border-radius: 8px; font-weight: 900; font-size: 16px; }
        .button-sub { font-size: 11px; color: #9ca3af; margin-top: 8px; }
        .ask { background-color: #f9fafb; border-left: 3px solid #db2777; padding: 16px 20px; margin: 24px 0; font-size: 13px; color: #374151; }
        .sign { margin-top: 24px; font-size: 13px; }
        .footer { background-color: #f3f4f6; text-align: center; padding: 20px; font-size: 11px; color: #6b7280; line-height: 1.7; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MotoHub 愛車ガレージ</h1>
        </div>
        <div class="content">
            <p class="lead">{{ $name }}さん</p>
            <p class="lead">MotoHubをご利用いただきありがとうございます。運営の内田です。<br>
                このたびMotoHubに、新しい機能が加わりましたのでお知らせします。</p>

            <div class="feature">
                <h2>■ 愛車ガレージ</h2>
                <p>給油レシートを撮るだけで、燃費・維持費を自動で記録・グラフ化できます。整備やカスタムの記録も残せるので、維持費が「なんとなく」から「数字」になります。</p>
            </div>

            <div class="feature">
                <h2>■ 公開プロフィール（フォロー・いいねも）</h2>
                <p>あなたの愛車を公開プロフィールページで紹介できるようになりました。（他のライダーをフォローしたり、いいねを送ったりもできます）</p>
            </div>

            <div class="button-container">
                <a class="button" href="https://www.motohub.jp/garage?utm_source=email&utm_medium=announcement&utm_campaign=garage_launch">あなたも愛車を記録しよう（無料で始める）</a>
                <div class="button-sub">レシートを撮るだけで燃費・維持費が残せる。無料。</div>
            </div>

            <div class="ask">
                そして、ひとつだけ正直なお願いをさせてください。<br>
                MotoHubはまだ小さなサービスで、ユーザーも決して多くありません。でも、だからこそ、いま使ってくださっているあなたの声が本当に貴重です。実際に使ってみた感想や「こうだったらいいのに」を、ぜひ聞かせてもらえませんか。<strong>このメールにそのままご返信いただければ、内田が直接拝見します。</strong>あなたの一言が、MotoHubを次に進める力になります。
            </div>

            <p>ぜひ、あなたの愛車を登録して使ってみてください。<br>これからもMotoHubをよろしくお願いいたします。</p>

            <p class="sign">MotoHub<br>内田</p>
        </div>
        <div class="footer">
            このメールはMotoHubに登録された方にお送りしています。<br>
            今後この種のお知らせが不要な場合は、お手数ですがこのメールにご返信ください。配信を停止いたします。
        </div>
    </div>
</body>
</html>
