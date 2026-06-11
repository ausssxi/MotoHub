<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| model:dedup — 安全ゲート ＋ マージ正当性
|--------------------------------------------------------------------------
| DedupBikeModels は破壊的なので二重ゲート（--execute + --i-have-a-backup）と
| グループ単位の手動承認を持つ。ここでは「ゲートが効くこと（書き込まない）」と
| 「承認時にマージが正しく行われること（付け替え・301化・slug譲渡）」を検証する。
*/

/** ユニークな source_url を持つ listing を1件作る。返り値は listing id。 */
function makeListing(int $modelId, int $siteId, bool $soldOut = false): int
{
    static $n = 0;
    $n++;

    return DB::table('listings')->insertGetId([
        'bike_model_id' => $modelId,
        'site_id' => $siteId,
        'source_url' => "https://example.test/listing/{$n}",
        'is_sold_out' => $soldOut,
        'needs_reindex' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** market_price_logs に1行作る（複合unique = bike_model_id + recorded_at）。 */
function makeMarketLog(int $modelId, string $recordedAt, int $avgPrice): void
{
    DB::table('market_price_logs')->insert([
        'bike_model_id' => $modelId,
        'avg_price' => $avgPrice,
        'min_price' => $avgPrice,
        'max_price' => $avgPrice,
        'listing_count' => 1,
        'recorded_at' => $recordedAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** bike_model_videos に1行作る（複合unique = bike_model_id + video_id）。 */
function makeVideo(int $modelId, string $videoId, string $title = 'v'): void
{
    DB::table('bike_model_videos')->insert([
        'bike_model_id' => $modelId,
        'video_id' => $videoId,
        'title' => $title,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** categories に1行作る（bike_models.category_id の FK 先）。 */
function makeCategory(int $id, string $name): void
{
    DB::table('categories')->insert([
        'id' => $id,
        'name' => $name,
        'sort_order' => 999,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    // Manufacturer の $fillable は ['slug'] のみ。name は mass-assignment 対象外なので forceCreate。
    $this->maker = Manufacturer::forceCreate(['name' => 'ホンダ']);
    $this->siteId = DB::table('sites')->insertGetId([
        'name' => 'TestSite',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

/**
 * 在庫の多い canonical(A) と少ない dupe(B) を、表記ゆれで同一キーに畳まれる名前で作る。
 * A="レブル 250"(半角空白), B="レブル２５０"(全角数字) → normalizeKey で双方 "レブル250"。
 *
 * @return array{0:BikeModel,1:BikeModel}
 */
function makeRebelPair(Manufacturer $maker, int $siteId, ?string $dupeSlug = null): array
{
    $canonical = BikeModel::create([
        'manufacturer_id' => $maker->id,
        'name' => 'レブル 250',
        'displacement' => 250,
        'slug' => null,
    ]);
    $dupe = BikeModel::create([
        'manufacturer_id' => $maker->id,
        'name' => 'レブル２５０',
        'displacement' => 250,
        'slug' => $dupeSlug,
    ]);

    // canonical: active 3 / dupe: active 1 + sold-out 1（canonical 選定は active 数で決まる）
    makeListing($canonical->id, $siteId);
    makeListing($canonical->id, $siteId);
    makeListing($canonical->id, $siteId);
    makeListing($dupe->id, $siteId);
    makeListing($dupe->id, $siteId, soldOut: true);

    return [$canonical, $dupe];
}

// ───────────────────────── 安全ゲート ─────────────────────────

it('既定（dry-run）はDBを一切変更しない', function () {
    [$canonical, $dupe] = makeRebelPair($this->maker, $this->siteId);

    $this->artisan('model:dedup')
        ->assertExitCode(0);

    expect($dupe->fresh()->merged_into_id)->toBeNull();
    expect(DB::table('listings')->where('bike_model_id', $dupe->id)->count())->toBe(2);
    expect(DB::table('listings')->where('bike_model_id', $canonical->id)->count())->toBe(3);
});

it('--execute でも --i-have-a-backup が無ければFAILUREで中断し書き込まない', function () {
    [, $dupe] = makeRebelPair($this->maker, $this->siteId);

    $this->artisan('model:dedup', ['--execute' => true])
        ->expectsOutputToContain('🚫')
        ->assertExitCode(1);

    expect($dupe->fresh()->merged_into_id)->toBeNull();
    expect(DB::table('listings')->where('bike_model_id', $dupe->id)->count())->toBe(2);
});

it('承認プロンプトでスキップ(n)を選ぶとマージしない', function () {
    [$canonical, $dupe] = makeRebelPair($this->maker, $this->siteId);

    $this->artisan('model:dedup', ['--execute' => true, '--i-have-a-backup' => true])
        ->expectsQuestion('このグループを統合しますか？', 'n')
        ->assertExitCode(0);

    expect($dupe->fresh()->merged_into_id)->toBeNull();
    expect(DB::table('listings')->where('bike_model_id', $dupe->id)->count())->toBe(2);
});

// ───────────────────────── マージ正当性 ─────────────────────────

it('承認(y)で dupe を canonical に統合する: 付け替え・301化・再インデックスフラグ', function () {
    [$canonical, $dupe] = makeRebelPair($this->maker, $this->siteId);

    $this->artisan('model:dedup', ['--execute' => true, '--i-have-a-backup' => true])
        ->expectsQuestion('このグループを統合しますか？', 'y')
        ->assertExitCode(0);

    // dupe は merged_into_id で canonical を指す（=301シグナル＋一覧除外）
    expect($dupe->fresh()->merged_into_id)->toBe($canonical->id);
    expect($canonical->fresh()->merged_into_id)->toBeNull();

    // listing は全件（sold-out含む）canonical へ付け替え
    expect(DB::table('listings')->where('bike_model_id', $dupe->id)->count())->toBe(0);
    expect(DB::table('listings')->where('bike_model_id', $canonical->id)->count())->toBe(5);

    // 付け替えた listing には needs_reindex が立つ
    expect(
        DB::table('listings')->where('bike_model_id', $canonical->id)->where('needs_reindex', true)->count()
    )->toBe(2);
});

it('canonical は active 在庫が多い行が選ばれる（在庫少の方が dupe になる）', function () {
    // 名前順では dupe が先に来るよう仕込んでも、在庫数で canonical が決まることを確認
    $low = BikeModel::create(['manufacturer_id' => $this->maker->id, 'name' => 'レブル 250', 'displacement' => 250]);
    $high = BikeModel::create(['manufacturer_id' => $this->maker->id, 'name' => 'レブル２５０', 'displacement' => 250]);
    makeListing($low->id, $this->siteId);
    makeListing($high->id, $this->siteId);
    makeListing($high->id, $this->siteId);

    $this->artisan('model:dedup', ['--execute' => true, '--i-have-a-backup' => true])
        ->expectsQuestion('このグループを統合しますか？', 'y')
        ->assertExitCode(0);

    expect($high->fresh()->merged_into_id)->toBeNull();
    expect($low->fresh()->merged_into_id)->toBe($high->id);
});

it('canonical が slug 無・dupe が slug 有なら clean slug を survivor へ譲渡する', function () {
    [$canonical, $dupe] = makeRebelPair($this->maker, $this->siteId, dupeSlug: 'rebel-250');

    $this->artisan('model:dedup', ['--execute' => true, '--i-have-a-backup' => true])
        ->expectsQuestion('このグループを統合しますか？', 'y')
        ->assertExitCode(0);

    // slug は canonical に移り、dupe からは外れる（unique(mfr_id,slug) 衝突回避のため先に空ける）
    expect($canonical->fresh()->slug)->toBe('rebel-250');
    expect($dupe->fresh()->slug)->toBeNull();
});

it('slug譲渡は slug のみ移し category_id は変更しない（canonical のカテゴリを保持）', function () {
    makeCategory(10, 'クルーザー');
    makeCategory(22, '未分類');

    // canonical(slug無・cat=22・在庫多) を donor(slug=rebel-250・cat=10・在庫少) で救済
    $canonical = BikeModel::create([
        'manufacturer_id' => $this->maker->id, 'name' => 'レブル 250',
        'displacement' => 250, 'slug' => null, 'category_id' => 22,
    ]);
    $dupe = BikeModel::create([
        'manufacturer_id' => $this->maker->id, 'name' => 'レブル２５０',
        'displacement' => 250, 'slug' => 'rebel-250', 'category_id' => 10,
    ]);
    makeListing($canonical->id, $this->siteId);
    makeListing($canonical->id, $this->siteId);
    makeListing($dupe->id, $this->siteId);

    $this->artisan('model:dedup', ['--execute' => true, '--i-have-a-backup' => true])
        ->expectsQuestion('このグループを統合しますか？', 'y')
        ->assertExitCode(0);

    // slug だけ譲り受け、category_id は canonical の元値(22)のまま（donor の 10 は継がない）
    $fresh = $canonical->fresh();
    expect($fresh->slug)->toBe('rebel-250');
    expect($fresh->category_id)->toBe(22);
    expect($dupe->fresh()->merged_into_id)->toBe($canonical->id);
});

// ───────────────────────── 複合unique衝突パス（repointWithUnique） ─────────────────────────

it('複合unique衝突: canonical既存と衝突する dupe行は削除し非衝突行のみ canonical へ付け替える', function () {
    [$canonical, $dupe] = makeRebelPair($this->maker, $this->siteId);

    // 同一 recorded_at(6/01) で衝突 → canonical既存(999999)が残り dupe(111111)は削除されるべき
    makeMarketLog($canonical->id, '2026-06-01', 999999);
    makeMarketLog($dupe->id, '2026-06-01', 111111);
    // 衝突しない dupe 行(5/01) は canonical へ付け替え
    makeMarketLog($dupe->id, '2026-05-01', 222222);

    // 衝突を握り潰さずトランザクションが完走する（unique例外で落ちない）こと自体が検証点
    $this->artisan('model:dedup', ['--execute' => true, '--i-have-a-backup' => true])
        ->expectsQuestion('このグループを統合しますか？', 'y')
        ->assertExitCode(0);

    expect(DB::table('market_price_logs')->where('bike_model_id', $dupe->id)->count())->toBe(0);
    expect(DB::table('market_price_logs')->where('bike_model_id', $canonical->id)->count())->toBe(2);

    // 6/01 は canonical 既存(999999)が生存・dupe(111111)が消滅 / 5/01 は付け替わって生存
    expect((int) DB::table('market_price_logs')
        ->where('bike_model_id', $canonical->id)->where('recorded_at', '2026-06-01')->value('avg_price'))->toBe(999999);
    expect((int) DB::table('market_price_logs')
        ->where('bike_model_id', $canonical->id)->where('recorded_at', '2026-05-01')->value('avg_price'))->toBe(222222);
});

it('複合unique衝突(dupe同士が同一video_id共有): 群全体で1行に正規化し例外を出さない', function () {
    // canonical + 2 dupe の3メンバー群（同一正規化キー「レブル250」）
    $canonical = BikeModel::create(['manufacturer_id' => $this->maker->id, 'name' => 'レブル 250', 'displacement' => 250]);
    $d1 = BikeModel::create(['manufacturer_id' => $this->maker->id, 'name' => 'レブル２５０', 'displacement' => 250]);
    $d2 = BikeModel::create(['manufacturer_id' => $this->maker->id, 'name' => 'レブル　２５０', 'displacement' => 250]);
    makeListing($canonical->id, $this->siteId);
    makeListing($canonical->id, $this->siteId);
    makeListing($canonical->id, $this->siteId);
    makeListing($d1->id, $this->siteId);
    makeListing($d2->id, $this->siteId);

    // canonical は別動画。2つの dupe が同一 video_id を共有（canonical は未保有）→ 旧実装は unique 違反を投げる
    makeVideo($canonical->id, 'v-canon');
    makeVideo($d1->id, 'v-shared');
    makeVideo($d2->id, 'v-shared');

    $this->artisan('model:dedup', ['--execute' => true, '--i-have-a-backup' => true])
        ->expectsQuestion('このグループを統合しますか？', 'y')
        ->assertExitCode(0);

    // 例外なくマージ完了。canonical 配下は v-canon + v-shared の2行、shared は1行だけに正規化
    expect(DB::table('bike_model_videos')->where('bike_model_id', $canonical->id)->count())->toBe(2);
    expect(DB::table('bike_model_videos')->where('bike_model_id', $canonical->id)->where('video_id', 'v-shared')->count())->toBe(1);
    expect(DB::table('bike_model_videos')->whereIn('bike_model_id', [$d1->id, $d2->id])->count())->toBe(0);
    expect($d1->fresh()->merged_into_id)->toBe($canonical->id);
    expect($d2->fresh()->merged_into_id)->toBe($canonical->id);
});

// ───────────────────────── manual ゲート（排気量不一致） ─────────────────────────

it('排気量不一致グループは manual 扱いで --force 無しならスキップ', function () {
    $a = BikeModel::create(['manufacturer_id' => $this->maker->id, 'name' => 'CB 400', 'displacement' => 400]);
    $b = BikeModel::create(['manufacturer_id' => $this->maker->id, 'name' => 'ＣＢ４００', 'displacement' => 250]);
    makeListing($a->id, $this->siteId);
    makeListing($b->id, $this->siteId);

    // manual はプロンプト自体が出ない（承認を期待しない）
    $this->artisan('model:dedup', ['--execute' => true, '--i-have-a-backup' => true])
        ->expectsOutputToContain('スキップ(manual)')
        ->assertExitCode(0);

    expect($a->fresh()->merged_into_id)->toBeNull();
    expect($b->fresh()->merged_into_id)->toBeNull();
});

it('排気量不一致でも --force + 承認なら canonical へ統合できる', function () {
    $a = BikeModel::create(['manufacturer_id' => $this->maker->id, 'name' => 'CB 400', 'displacement' => 400]);
    $b = BikeModel::create(['manufacturer_id' => $this->maker->id, 'name' => 'ＣＢ４００', 'displacement' => 250]);
    makeListing($a->id, $this->siteId);
    makeListing($a->id, $this->siteId);
    makeListing($b->id, $this->siteId);

    $this->artisan('model:dedup', ['--execute' => true, '--i-have-a-backup' => true, '--force' => true])
        ->expectsQuestion('このグループを統合しますか？', 'y')
        ->assertExitCode(0);

    expect($a->fresh()->merged_into_id)->toBeNull();
    expect($b->fresh()->merged_into_id)->toBe($a->id);
    expect(DB::table('listings')->where('bike_model_id', $a->id)->count())->toBe(3);
});
