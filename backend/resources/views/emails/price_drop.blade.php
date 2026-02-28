<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    {{-- メーラー対応のため、Tailwindではなく安全なインラインCSS風に記述 --}}
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #f9fafb; color: #111827; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .header { background-color: #2563eb; color: #ffffff; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 900; }
        .content { padding: 32px; }
        .bike-name { font-size: 18px; font-weight: bold; margin-bottom: 16px; color: #1f2937; }
        .price-box { background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 24px; }
        .old-price { text-decoration: line-through; color: #9ca3af; font-size: 16px; }
        .new-price { color: #dc2626; font-size: 24px; font-weight: 900; margin-left: 12px; }
        .diff { color: #dc2626; font-weight: bold; display: block; margin-top: 8px; font-size: 14px; }
        .button-container { text-align: center; margin-top: 32px; }
        .button { display: inline-block; background-color: #ef4444; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; }
        .footer { background-color: #f3f4f6; text-align: center; padding: 20px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MotoHub 値下げアラート</h1>
        </div>
        <div class="content">
            <p><strong>{{ $user->name }}</strong> さん</p>
            <p>お気に入りに追加している車両が値下がりしました！<br>他の方に買われてしまう前に、お早めにご確認ください。</p>
            
            @php
                $bikeName = $listing->title ?? $listing->bikeModel?->name ?? 'バイク';
                $oldPriceMan = number_format($priceHistory->old_price / 10000, 1);
                $newPriceMan = number_format($priceHistory->new_price / 10000, 1);
                $diffMan = number_format(($priceHistory->old_price - $priceHistory->new_price) / 10000, 1);
            @endphp

            <div class="bike-name">{{ $bikeName }}</div>
            
            <div class="price-box">
                <span class="old-price">旧価格: {{ $oldPriceMan }}万円</span>
                <span class="new-price">新価格: {{ $newPriceMan }}万円</span>
                <span class="diff">⬇︎ {{ $diffMan }}万円 お買い得になりました！</span>
            </div>

            <div class="button-container">
                <a href="{{ route('bikes.show', $listing->id) }}" class="button">車両の詳細を見る</a>
            </div>
        </div>
        <div class="footer">
            <p>※このメールはMotoHubでお気に入り登録された車両の価格変動を自動的にお知らせするものです。</p>
            <p>© {{ date('Y') }} MotoHub.</p>
        </div>
    </div>
</body>
</html>