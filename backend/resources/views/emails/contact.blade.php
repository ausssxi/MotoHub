{{-- 管理者宛のお問い合わせ通知メール --}}
<h2>MotoHub お問い合わせ通知</h2>

<p>サイトから新しいお問い合わせがありました。内容は以下の通りです。</p>

<hr>

<p><strong>■ お名前</strong></p>
<p>{{ $data['name'] }} 様</p>

<p><strong>■ メールアドレス</strong></p>
<p>{{ $data['email'] }}</p>

<p><strong>■ お問い合わせ項目</strong></p>
<p>
    @php
        $categories = [
            'feedback' => 'サービスへのご要望・改善案',
            'report'   => '不具合・誤情報の報告',
            'business' => 'ビジネス・提携に関するご相談',
            'other'    => 'その他',
        ];
        echo $categories[$data['category']] ?? '不明';
    @endphp
</p>

<p><strong>■ お問い合わせ内容</strong></p>
<p>{!! nl2br(e($data['message'])) !!}</p>

<hr>

<p>送信時刻: {{ now()->format('Y-m-d H:i:s') }}</p>
<p>送信元IP: {{ request()->ip() }}</p>