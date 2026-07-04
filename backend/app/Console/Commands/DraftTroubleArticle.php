<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * 症状トラブル記事の「下書き」を機械生成する。
 *
 * ⚠️ 重要（この機能の存在理由）:
 *   このコマンドは下書き(status=draft・published_at=null)しか作らない。
 *   自動公開・スケジュール実行の経路を構造的に持たない。公開は必ず人間（監修者）が
 *   管理画面で監修・編集してから行う。→ Kernelスケジュールには絶対に登録しないこと。
 */
final class DraftTroubleArticle extends Command
{
    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const MAX_TOKENS = 8000;

    protected $signature = 'blog:draft-trouble
        {--symptom= : 症状名（必須）例「原付のエンジンがかからない」}
        {--keyword= : 狙う検索キーワード（必須）例「原付 エンジン かからない」}
        {--notes= : 監修者からの指示（任意）}
        {--symptom-slug= : 診断ツールのディープリンク用スラッグ（任意・config/diagnosis.php の症状キー）}';

    protected $description = 'Claudeで症状トラブル記事の下書きを1本生成する（下書きのみ・自動公開しない）';

    public function handle(): int
    {
        $symptom = trim((string) $this->option('symptom'));
        $keyword = trim((string) $this->option('keyword'));
        $notes = trim((string) $this->option('notes'));

        if ($symptom === '' || $keyword === '') {
            $this->error('--symptom と --keyword は必須です。');

            return self::FAILURE;
        }

        $apiKey = config('services.anthropic.api_key');
        if (empty($apiKey)) {
            $this->error('services.anthropic.api_key が未設定です。');

            return self::FAILURE;
        }

        $author = $this->resolveAuthor();
        if (! $author) {
            $this->error('著者にできるユーザー（role=admin/writer）が見つかりません。');

            return self::FAILURE;
        }

        // 診断ツールへのディープリンク（有効スラッグならクエリ付き・無ければ素の /trouble）
        $slug = trim((string) $this->option('symptom-slug'));
        $validSlugs = array_keys(config('diagnosis.symptoms', []));
        $diagnosisLink = ($slug !== '' && in_array($slug, $validSlugs, true))
            ? "/trouble?symptom={$slug}"
            : '/trouble';

        $existingTags = BlogTag::query()->pluck('name')->all();

        $this->info("下書き生成中… symptom={$symptom} / keyword={$keyword}");

        // JSONパース失敗はリトライ1回まで
        $data = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $text = $this->callClaude($apiKey, $symptom, $keyword, $notes, $diagnosisLink, $existingTags);
            if ($text === null) {
                $this->error('Claude API 呼び出しに失敗しました。下書きは作成していません。');

                return self::FAILURE;
            }

            $data = $this->parseJson($text);
            if ($data !== null) {
                break;
            }
            $this->warn("JSONパース失敗（試行 {$attempt}/2）");
        }

        if ($data === null) {
            $this->error('生成結果のパースに失敗しました。下書きは作成していません。');

            return self::FAILURE;
        }

        $post = $this->saveDraft($data, $author->id, $symptom, $keyword);

        $this->info('✅ 下書きを作成しました（未公開・要監修）');
        $this->table(['項目', '値'], [
            ['ID', $post->id],
            ['タイトル', $post->title],
            ['スラッグ', $post->slug],
            ['ステータス', $post->status],
            ['管理画面', route('admin.blog.posts.edit', $post->id)],
        ]);

        return self::SUCCESS;
    }

    private function resolveAuthor(): ?User
    {
        return User::query()->where('role', 'admin')->first()
            ?? User::query()->where('role', 'writer')->first();
    }

    /**
     * Claude を1回呼び、本文テキスト（JSON文字列）を返す。失敗時 null。
     */
    private function callClaude(string $apiKey, string $symptom, string $keyword, string $notes, string $diagnosisLink, array $existingTags): ?string
    {
        $system = <<<'SYS'
            あなたは日本の原付・バイク整備に詳しいプロのライター兼整備士です。
            読者の不安を煽らず、断定しすぎず、丁寧な「です・ます」調で書きます。
            医療・法律のような過度な断定は避け、安全に関わる判断は必ず販売店・整備士に相談するよう促します。
            出力は指定されたJSONのみ。前置き・後書き・コードフェンスは付けないこと。
            SYS;

        $tagList = empty($existingTags) ? '（既存タグなし）' : implode('、', $existingTags);

        $notesLine = $notes !== '' ? "監修者からの指示（必ず反映）: {$notes}" : '監修者からの指示: なし';

        $user = <<<PROMPT
            以下の条件で、症状トラブル対処の記事「下書き」を作成してください。

            ■ 症状: {$symptom}
            ■ 狙う検索キーワード: {$keyword}
            {$notesLine}

            ■ 本文の構成（この6部構成を必ず守る・各見出しは ## で）
            1. リード: 「かからない＝壊れたとは限らない」型の安心導入。この記事で何が分かるかを2〜3行で。
            2. 大前提: 症状を悪化させないための注意（例: セルを回し続けない 等）。
            3. 最初の10秒チェック: 単純な原因を除外するチェックリスト（箇条書き）。
            4. 切り分け（記事の肝）: 読者が観察できる違いで原因を大きく分岐させる。
            5. 自分で試せること／ここから先はお店の領域: DIYと店の境界を明示する。
            6. 締め: 安全に関わる判断は販売店・整備士へ、という免責と次アクション。

            ■ 本文に必ず入れる内部リンク（Markdownリンクで自然に）
            - 症状診断ツール: {$diagnosisLink} （リードか切り分けセクションで「まず診断ツールで切り分け」と促す）
            - 整備・修理店検索: /shops/repair （「ここから先はお店の領域」セクション内）
            - 関連パーツがある症状なら、パーツ価格比較 /parts へのリンク（例: バッテリーの症状ならバッテリー比較）。無ければ入れなくてよい。

            ■ その他の条件
            - 本文は Markdown。分量は2,500〜3,500字目安。
            - タイトルは32文字前後。可能なら【】で切り分け軸を示す既存の型に寄せる。
            - スラッグは英語のケバブケース（例: gentsuki-engine-wont-start の型）。
            - meta_description は120字前後。
            - タグは次の既存タグから最大3つ選ぶ（新規が妥当なら1つだけ新規可）: {$tagList}

            ■ 出力（このJSONオブジェクトだけを返す。他の文字は一切出力しない）
            {
              "title": "...",
              "slug": "...",
              "meta_description": "...",
              "tags": ["...", "..."],
              "body_markdown": "..."
            }
            PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post(self::API_ENDPOINT, [
                'model' => config('services.anthropic.model'),
                'max_tokens' => self::MAX_TOKENS,
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $user],
                ],
            ]);
        } catch (\Throwable $e) {
            $this->warn('API通信エラー: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            $this->warn("APIエラー: {$response->status()}");

            return null;
        }

        return $response->json('content.0.text');
    }

    /**
     * @return array{title:string,slug:string,meta_description:string,tags:array,body_markdown:string}|null
     */
    private function parseJson(string $text): ?array
    {
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/i', '', (string) $text);

        $decoded = json_decode(trim((string) $text), true);

        if (! is_array($decoded)) {
            return null;
        }

        foreach (['title', 'slug', 'meta_description', 'body_markdown'] as $key) {
            if (empty($decoded[$key]) || ! is_string($decoded[$key])) {
                return null;
            }
        }
        if (! isset($decoded['tags']) || ! is_array($decoded['tags'])) {
            $decoded['tags'] = [];
        }

        return $decoded;
    }

    private function saveDraft(array $data, int $authorId, string $symptom, string $keyword): BlogPost
    {
        // 生成物であることを示すフラグは専用カラム draft_note に持たせる（管理画面限定表示）。
        // body は生成本文そのものだけにし、マーカーで汚さない＝消し忘れ公開でも一般表示に痕跡が残らない。
        $note = mb_substr(
            'AI下書き（要監修） 生成:'.now()->format('Y-m-d H:i').' / symptom:'.$symptom.' / keyword:'.$keyword,
            0,
            255,
        );

        $post = new BlogPost([
            'author_id' => $authorId,
            'title' => mb_substr($data['title'], 0, 255),
            'slug' => $this->uniqueSlug($data['slug']),
            'body' => $data['body_markdown'],
            'meta_description' => mb_substr($data['meta_description'], 0, 300),
            'status' => 'draft',      // ← 絶対に published にしない
            'draft_note' => $note,    // ← 生成物フラグ（管理画面のみ表示）
            'published_at' => null,   // ← いかなる経路でも公開状態にしない
        ]);
        $post->save();

        $this->attachTags($post, $data['tags']);

        return $post;
    }

    private function uniqueSlug(string $raw): string
    {
        $base = Str::slug($raw) ?: Str::lower(Str::random(14));
        $slug = mb_substr($base, 0, 255);
        $i = 2;
        while (BlogPost::withTrashed()->where('slug', $slug)->exists()) {
            $slug = mb_substr($base, 0, 250).'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function attachTags(BlogPost $post, array $tagNames): void
    {
        $ids = [];
        foreach ($tagNames as $name) {
            if (! is_string($name) || trim($name) === '') {
                continue;
            }
            $tag = BlogTag::firstOrCreate(['name' => trim($name)]);
            $ids[] = $tag->id;
        }
        if ($ids) {
            $post->tags()->sync($ids);
        }
    }
}
