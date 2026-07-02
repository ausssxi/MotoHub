{{-- 管理者宛: 店舗登録の投稿通知（承認待ち） --}}
<h2>MotoHub 店舗登録の投稿（承認待ち）</h2>

<p>ユーザーから未掲載店舗の登録投稿がありました。Filament管理画面（店舗登録申請）で内容を確認し、承認・統合・却下してください。</p>

<hr>

<p><strong>■ 店名</strong></p>
<p>{{ $submission->shop_name }}</p>

<p><strong>■ 所在地</strong></p>
<p>{{ $submission->prefecture }}{{ $submission->city }}{{ $submission->address ? ' '.$submission->address : '' }}</p>

@if($submission->phone)
<p><strong>■ 電話</strong></p>
<p>{{ $submission->phone }}</p>
@endif

@if($submission->website_url)
<p><strong>■ サイト</strong></p>
<p>{{ $submission->website_url }}</p>
@endif

@if(!empty($submission->service_tags))
<p><strong>■ 対応サービス</strong></p>
<p>{{ implode(' / ', $submission->service_tags) }}</p>
@endif

@if(!empty($submission->acceptance_flags))
<p><strong>■ 受け入れ情報</strong></p>
<ul>
    @foreach($submission->acceptance_flags as $flag)
        <li>{{ \App\Models\ShopAcceptanceReport::FLAGS[$flag] ?? $flag }}</li>
    @endforeach
</ul>
@endif

@if($submission->comment)
<p><strong>■ コメント</strong></p>
<p>{!! nl2br(e($submission->comment)) !!}</p>
@endif

<p><strong>■ 投稿者</strong></p>
<p>{{ $submission->submitter_name ?: '名無しライダー' }}{{ $submission->user_id ? '（ログインユーザー）' : '（匿名）' }}</p>

<hr>
<p style="color:#888;font-size:12px;">※ この投稿は未承認です。承認するまでサイトには掲載されません。</p>
