<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @param int $h2 見出し(h2)の数 */
function adsPost(int $h2 = 4, string $slug = 'ads-post'): BlogPost
{
    $author = User::factory()->create(['role' => 'admin']);

    $body = '';
    for ($i = 1; $i <= $h2; $i++) {
        $body .= "## 見出し{$i}\n\n本文の段落です。テキストが続きます。\n\n";
    }

    return BlogPost::create([
        'author_id' => $author->id,
        'title' => '広告テスト記事',
        'slug' => $slug,
        'body' => $body,
        'status' => 'published',
        'published_at' => now(),
    ]);
}

function enableAds(): void
{
    config()->set('adsense.enabled', true);
    config()->set('adsense.client', 'ca-pub-test');
    config()->set('adsense.slots.blog_mid', 'slot-mid');
    config()->set('adsense.slots.blog_bottom', 'slot-bottom');
}

it('outputs nothing ad-related when disabled', function () {
    config()->set('adsense.enabled', false);
    $post = adsPost();

    $res = $this->get('/blog/'.$post->slug)->assertOk();

    expect($res->getContent())->not->toContain('adsbygoogle');   // スクリプトも <ins> も無し
    expect($res->getContent())->not->toContain('class="ad-unit"'); // 広告ラベル入りの枠も無し
});

it('does not render an <ins> when slot IDs are empty (enabled)', function () {
    config()->set('adsense.enabled', true);
    config()->set('adsense.client', 'ca-pub-test');
    config()->set('adsense.slots.blog_mid', '');
    config()->set('adsense.slots.blog_bottom', '');
    $post = adsPost();

    $res = $this->get('/blog/'.$post->slug)->assertOk();

    // ローダーは出る（enabled+client）が、空の <ins> は出さない。
    expect(substr_count($res->getContent(), '<ins class="adsbygoogle"'))->toBe(0);
});

it('renders exactly two <ins> and loads the script once when enabled with slots', function () {
    enableAds();
    $post = adsPost(4);

    $res = $this->get('/blog/'.$post->slug)->assertOk();
    $html = $res->getContent();

    expect(substr_count($html, '<ins class="adsbygoogle"'))->toBe(2);       // 中盤＋末尾
    expect(substr_count($html, 'pagead/js/adsbygoogle.js'))->toBe(1);        // ローダーは head に1回だけ
    expect(substr_count($html, 'data-ad-slot="slot-mid"'))->toBe(1);
    expect(substr_count($html, 'data-ad-slot="slot-bottom"'))->toBe(1);
});

it('places the mid unit before a middle h2 (not the first, not the intro)', function () {
    enableAds();
    $post = adsPost(4);
    $html = $this->get('/blog/'.$post->slug)->getContent();

    $midPos = strpos($html, 'data-ad-slot="slot-mid"');
    // 本文の <h2> タグ位置で判定（目次はテキスト重複するため <h2 タグで見る）。
    preg_match_all('/<h2\b/i', $html, $m, PREG_OFFSET_CAPTURE);
    $h2Offsets = array_column($m[0], 1);
    $before = count(array_filter($h2Offsets, fn ($o) => $o < $midPos));
    $after = count(array_filter($h2Offsets, fn ($o) => $o > $midPos));

    expect($before)->toBeGreaterThanOrEqual(1); // 記事冒頭・最初のh2より後
    expect($after)->toBeGreaterThanOrEqual(1);  // 末尾のh2より前（＝中盤）
});

it('does not render the mid unit when there are fewer than 3 h2', function () {
    enableAds();
    $post = adsPost(2); // h2 が2つ

    $res = $this->get('/blog/'.$post->slug)->assertOk();
    $html = $res->getContent();

    expect(substr_count($html, '<ins class="adsbygoogle"'))->toBe(1);   // 末尾枠のみ
    expect(substr_count($html, 'data-ad-slot="slot-mid"'))->toBe(0);
});

it('does not leak ads onto non-blog pages', function () {
    enableAds(); // 有効でも、広告を置いていないページには <ins> は出ない

    $res = $this->get('/touring/autumn')->assertOk();

    expect(substr_count($res->getContent(), '<ins class="adsbygoogle"'))->toBe(0);
});
