<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BikeModel;
use App\Models\ModelFitment;
use Illuminate\Support\Collection;

/**
 * 車種ページ「プラグの目安」ブロックのデータ解決（表示時計算・キャッシュbump不要）。
 * BatteryMaintenance と同設計：
 *  ・verified な plug 適合が無い車種はブロックを出さない（mode=none）。一般フォールバックはしない。
 *  ・型番は表示に出さない（熱価/必要本数の区分のみ）。型番の勝負ワードは適合表ページ(fitments.show)へ一本化＝カニバリ回避。
 *    ※型番は商品検索 keyword に「内部的に」だけ使う（表示ではない）。
 *  ・複数 frame_code：値が相違するキーは「型式による」に畳む。型番が相違なら商品カードを出さず検索リンク1本。
 *  ・数字は創作しない：全て verified な DB 値のみ。
 */
final class PlugMaintenance
{
    /**
     * @return array{
     *   mode:string,
     *   heat:?string, plugs:?string,
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

        // 型番は全行同一のときだけ商品 keyword に使う。相違（型式による）なら null → 検索リンクに逃がす。
        $partNo = self::single($rows, 'recommended_part_no');
        $productKeyword = $partNo !== null ? 'バイク スパークプラグ '.$partNo : null;

        return [
            'mode' => 'rich',
            'heat' => self::specField($rows, 'heat'),
            'plugs' => self::specField($rows, 'plugs'),
            'frame_count' => $rows->count(),
            'product_keyword' => $productKeyword,
            'fitment_url' => route('fitments.show', ['bikeModel' => $model->slug, 'task' => 'plug']),
            'search_url' => route('parts.compare', ['keyword' => $model->name.' スパークプラグ']),
            'sources' => self::sources($rows),
            'verified_at' => $rows->max('verified_at'),
        ];
    }

    /** verified な plug 適合行（型式順）。 */
    private static function verifiedRows(BikeModel $model): Collection
    {
        return ModelFitment::query()
            ->where('bike_model_id', $model->id)
            ->where('task', 'plug')
            ->verified()
            ->orderBy('frame_code')
            ->get();
    }

    /** spec のキーを行群で集約（全行同一→値／相違→「型式による」／全欠損→null）。 */
    private static function specField(Collection $rows, string $key): ?string
    {
        $vals = self::values($rows, "spec.{$key}");
        if ($vals->isEmpty()) {
            return null;
        }

        return $vals->count() > 1 ? '型式による' : (string) $vals->first();
    }

    /** 全行ユニークならその値、相違・欠損なら null。 */
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
