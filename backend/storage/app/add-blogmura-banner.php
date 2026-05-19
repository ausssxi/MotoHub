<?php
/**
 * Add にほんブログ村 banner to all 5 new blog articles.
 * Run inside Docker: php storage/app/add-blogmura-banner.php
 */
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BlogPost;

$banner = <<<'BANNER'

---

<a href="https://bike.blogmura.com/ranking/in?p_cid=11215019" target="_blank"><img src="https://b.blogmura.com/bike/88_31.gif" width="88" height="31" border="0" alt="にほんブログ村 バイクブログへ" /></a><br /><a href="https://bike.blogmura.com/ranking/in?p_cid=11215019" target="_blank">にほんブログ村</a>
BANNER;

$slugs = [
    '400cc-all-models-comparison-2026',
    'best-bikes-for-beginners-2026',
    'bike-market-forecast-summer-2026',
    '125cc-all-models-comparison-2026',
    // big-bike-annual-cost-comparison-2026 already has banner from gen-bigbike.php
];

foreach ($slugs as $slug) {
    $post = BlogPost::where('slug', $slug)->first();
    if (!$post) {
        echo "SKIP {$slug} — not found\n";
        continue;
    }

    // Check if banner already exists
    if (str_contains($post->body, 'blogmura.com')) {
        echo "SKIP {$slug} — banner already exists\n";
        continue;
    }

    $post->body .= $banner;
    $post->save();
    echo "OK {$slug} — banner added (body=" . mb_strlen($post->body) . " chars)\n";
}

// Also check the 250cc reference article
$ref = BlogPost::where('slug', '250cc-all-models-comparison-2026')->first();
if ($ref && !str_contains($ref->body, 'blogmura.com')) {
    $ref->body .= $banner;
    $ref->save();
    echo "OK 250cc-all-models-comparison-2026 — banner added\n";
} elseif ($ref) {
    echo "SKIP 250cc-all-models-comparison-2026 — banner already exists\n";
} else {
    echo "SKIP 250cc-all-models-comparison-2026 — not found\n";
}

echo "\nDone! All banners processed.\n";
