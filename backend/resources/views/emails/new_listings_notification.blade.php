<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; background-color: #f3f4f6; color: #333; line-height: 1.5; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; }
        .header { text-align: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .logo { font-size: 24px; font-weight: bold; color: #000; text-decoration: none; }
        .content { margin-bottom: 30px; }
        .bike-card { border: 1px solid #eee; border-radius: 8px; overflow: hidden; margin-bottom: 15px; }
        .bike-img { width: 100%; height: 200px; object-fit: cover; background-color: #eee; }
        .bike-info { padding: 15px; }
        .bike-title { font-size: 16px; font-weight: bold; margin: 0 0 5px; }
        .bike-price { color: #dc2626; font-size: 18px; font-weight: bold; }
        .bike-meta { font-size: 12px; color: #666; margin-top: 5px; }
        .btn { display: inline-block; background-color: #000; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px; }
        .footer { text-align: center; font-size: 12px; color: #999; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="{{ route('bikes.index') }}" class="logo">MotoHub</a>
        </div>

        <div class="content">
            <p>{{ $user->name }} 様</p>
            <p>
                保存された検索条件 <strong>「{{ $searchName }}」</strong> にマッチする新着バイクが見つかりました！
            </p>

            @foreach($listings as $bike)
            <div class="bike-card">
                @if(!empty($bike->images) && isset($bike->images[0]))
                    <img src="{{ $bike->images[0] }}" alt="{{ $bike->name }}" class="bike-img">
                @else
                    <div style="height: 200px; background: #eee; display: flex; align-items: center; justify-content: center; color: #999;">No Image</div>
                @endif
                <div class="bike-info">
                    <h3 class="bike-title">{{ $bike->name }}</h3>
                    <div class="bike-price">{{ $bike->total_price }}万円</div>
                    <div class="bike-meta">
                        {{ $bike->model_year }}年式 | {{ $bike->mileage }} | {{ $bike->prefecture }}
                    </div>
                    <div style="margin-top: 10px; text-align: right;">
                        <a href="{{ route('bikes.show', $bike->id) }}" class="btn">詳細を見る</a>
                    </div>
                </div>
            </div>
            @endforeach

            <div style="text-align: center; margin-top: 20px;">
                <a href="{{ route('dashboard') }}" style="color: #666; text-decoration: underline; font-size: 12px;">
                    検索条件の確認・変更はこちら
                </a>
            </div>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} MotoHub. All rights reserved.<br>
            このメールは MotoHub の検索条件アラート機能により送信されています。
        </div>
    </div>
</body>
</html>