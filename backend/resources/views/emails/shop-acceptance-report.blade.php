{{-- 管理者宛: 店舗受け入れ情報の投稿通知（承認待ち） --}}
<h2>MotoHub 店舗情報の投稿（承認待ち）</h2>

<p>ユーザーから店舗の受け入れ情報が投稿されました。Filament管理画面で内容を確認し、承認してください。</p>

<hr>

<p><strong>■ 店舗</strong></p>
<p>{{ $report->shop?->name ?? '(不明)' }}（ID: {{ $report->shop_id }}）</p>

<p><strong>■ 投稿者</strong></p>
<p>{{ $report->submitter_name ?: '名無しライダー' }}{{ $report->user_id ? '（ログインユーザー）' : '（匿名）' }}</p>

<p><strong>■ 報告された受け入れ情報</strong></p>
<ul>
    @foreach(\App\Models\ShopAcceptanceReport::FLAGS as $col => $label)
        @if($report->{$col})<li>{{ $label }}</li>@endif
    @endforeach
</ul>

@if($report->comment)
<p><strong>■ コメント</strong></p>
<p>{!! nl2br(e($report->comment)) !!}</p>
@endif

<p><strong>■ 投稿日時</strong></p>
<p>{{ $report->created_at?->format('Y/m/d H:i') }}</p>

<hr>
<p style="color:#888;font-size:12px;">※ この投稿は未承認です。承認するまでサイトには表示されません。</p>
