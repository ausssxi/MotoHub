@php
    // UTM は前例（garage_launch）に揃える。route() は APP_URL 基準で絶対URLを生成。
    $utm = '?utm_source=email&utm_medium=announcement&utm_campaign=new_features';
    $profileUrl = route('profile.edit').$utm;
    $garageUrl = route('mybikes.index').$utm;
    $contributionsUrl = route('mypage.contributions').$utm;
    $topUrl = route('bikes.index').$utm;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    {{-- メーラー対応のため Tailwind ではなくインラインCSS風（garage_launch 等と同流儀） --}}
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #f9fafb; color: #111827; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .header { background-color: #db2777; color: #ffffff; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 900; }
        .content { padding: 32px; line-height: 1.8; font-size: 14px; }
        .lead { margin: 0 0 20px; }
        .feature { background-color: #fdf2f8; border: 1px solid #fbcfe8; border-radius: 8px; padding: 16px 20px; margin-bottom: 16px; }
        .feature h2 { margin: 0 0 6px; font-size: 15px; font-weight: 900; color: #be185d; }
        .feature p { margin: 0 0 10px; font-size: 13px; color: #374151; }
        .feature a { display: inline-block; color: #be185d; font-weight: 700; font-size: 13px; text-decoration: none; }
        .ask { background-color: #f9fafb; border-left: 3px solid #db2777; padding: 16px 20px; margin: 24px 0; font-size: 13px; color: #374151; }
        .sign { margin-top: 24px; font-size: 13px; }
        .sign a { color: #6b7280; text-decoration: none; }
        .footer { background-color: #f3f4f6; text-align: center; padding: 20px; font-size: 11px; color: #6b7280; line-height: 1.7; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MotoHub からのお知らせ</h1>
        </div>
        <div class="content">
            <p class="lead">{{ $name }}さん</p>
            <p class="lead">MotoHubをご利用いただき、ありがとうございます。<br>
                個人で運営している内田（ウッチー）です。</p>
            <p class="lead">このたび、いくつか新しい機能を追加しましたので、お知らせします。</p>

            <div class="feature">
                <h2>▼ プロフィールアイコンを設定できるようになりました</h2>
                <p>アカウント設定から、あなたのアイコンを設定できます。愛車ガレージやレビュー、コメントに表示されます。</p>
                <a href="{{ $profileUrl }}">→ アカウント設定へ</a>
            </div>

            <div class="feature">
                <h2>▼ 愛車ガレージにコメント・いいねが付くように</h2>
                <p>ほかのユーザーが、あなたの愛車ガレージにコメントやいいねを付けられるようになりました。あなたのバイクを、みんなに見てもらえます。</p>
                <a href="{{ $garageUrl }}">→ 愛車ガレージへ</a>
            </div>

            <div class="feature">
                <h2>▼「マイコンテンツ」で投稿をまとめて管理</h2>
                <p>これまで書いたレビューやコメントを、マイページの「マイコンテンツ」で一覧・管理できるようになりました。</p>
                <a href="{{ $contributionsUrl }}">→ マイコンテンツへ</a>
            </div>

            <div class="ask">
                久しぶりに、ぜひ覗いてみてください。<br>
                ご感想やご要望があれば、<strong>このメールにそのままご返信いただけると嬉しいです。</strong>
            </div>

            <p class="sign">
                ────────────<br>
                MotoHub　内田（ウッチー）<br>
                <a href="{{ $topUrl }}">{{ $topUrl }}</a>
            </p>
        </div>
        <div class="footer">
            ※このメールはMotoHub会員の方にお送りしています。<br>
            ※配信停止をご希望の方は、このメールにご返信ください。以後お送りいたしません。
        </div>
    </div>
</body>
</html>
