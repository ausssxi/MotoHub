<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BikeModel;
use App\Models\ModelFitment;
use Illuminate\Support\Collection;

/**
 * 車種ページ「バッテリーの目安」ブロックのデータ解決（表示時計算・キャッシュbump不要）。
 *
 * 設計の肝（[[discussion-threads-ugc]] のオイル版と揃えつつ、カニバリ制約を厳守）:
 *  ・verified な battery 適合行が「無い」車種はブロック自体を出さない（mode=none）。
 *    → 排気量からバッテリー規格は断定できず、一般フォールバックは thin/duplicate content
 *      になるだけで SEO・アフィリ両面の受け皿にならないため（オイルとの決定的な違い）。
 *  ・型番（recommended_part_no・compatible_part_nos）は表示に出さない。区分（voltage/capacity/type）
 *    のみ表示し、「型番」の勝負ワードは適合表ページ（fitments.show）へ一本化する（カニバリ回避）。
 *    ※型番は商品検索 keyword に「内部的に」だけ使う（表示ではないのでカニバらず、商品マッチ精度が上がる）。
 *  ・複数 frame_code 対応必須：行間で値が違うキーは「型式による」に畳む（単一値前提にしない）。
 *    型番が複数（型式による）のときは商品カードを出さず、検索リンク1本に逃がす（API負荷回避）。
 *  ・数字は創作しない：全て verified な DB 値のみ。
 */
final class BatteryMaintenance
{
    /**
     * @return array{
     *   mode:string,
     *   voltage:?string, capacity:?string, type:?string,
     *   frame_count:int,
     *   product_keyword:?string,
     *   fitment_url:string, search_url:string,
     *   sources:array<int,array{name:string,url:?string}>,
     *   verified_at:mixed
     * }
     */
    public static function forModel(BikeModel $model): array
    {
        $rows = self::verifiedRows($model);

        if ($rows->isEmpty()) {
            return ['mode' => 'none'];
        }

        // 型番は「全行同一のときだけ」商品 keyword に使う。相違（型式による）なら null → 検索リンクに逃がす。
        $partNo = self::single($rows, 'recommended_part_no');
        $productKeyword = $partNo !== null ? 'バイク バッテリー '.$partNo : null;

        return [
            'mode' => 'rich',
            'voltage' => self::specField($rows, 'voltage'),
            'capacity' => self::specField($rows, 'capacity'),
            'type' => self::specField($rows, 'type'),
            'frame_count' => $rows->count(),
            'product_keyword' => $productKeyword,
            'fitment_url' => route('fitments.show', ['bikeModel' => $model->slug, 'task' => 'battery']),
            'search_url' => route('parts.search', ['keyword' => $model->name.' バッテリー']),
            'sources' => self::sources($rows),
            'verified_at' => $rows->max('verified_at'),
        ];
    }

    /** verified な battery 適合行（型式順）。 */
    private static function verifiedRows(BikeModel $model): Collection
    {
        return ModelFitment::query()
            ->where('bike_model_id', $model->id)
            ->where('task', 'battery')
            ->verified()
            ->orderBy('frame_code')
            ->get();
    }

    /**
     * spec のキーを行群で集約（全行同一→値／相違→「型式による」／全欠損→null）。
     * ※区分レベルなので「型式による」も表示可（型番ではない）。
     */
    private static function specField(Collection $rows, string $key): ?string
    {
        $vals = self::values($rows, "spec.{$key}");

        if ($vals->isEmpty()) {
            return null;
        }

        return $vals->count() > 1 ? '型式による' : (string) $vals->first();
    }

    /** 行群で当該パスの値が全行ユニークならその値、相違・欠損なら null。 */
    private static function single(Collection $rows, string $path): ?string
    {
        $vals = self::values($rows, $path);

        return $vals->count() === 1 ? (string) $vals->first() : null;
    }

    /** パスの非空ユニーク値コレクション。 */
    private static function values(Collection $rows, string $path): Collection
    {
        return $rows
            ->map(fn ($r) => data_get($r, $path))
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->unique()
            ->values();
    }

    /**
     * 全行の出典を name で重複排除して集約。
     *
     * @return array<int,array{name:string,url:?string}>
     */
    private static function sources(Collection $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            foreach ([[$r->source_1_name, $r->source_1_url], [$r->source_2_name, $r->source_2_url]] as [$name, $url]) {
                $name = trim((string) $name);
                if ($name !== '' && ! isset($out[$name])) {
                    $out[$name] = ['name' => $name, 'url' => $url ?: null];
                }
            }
        }

        return array_values($out);
    }
}
