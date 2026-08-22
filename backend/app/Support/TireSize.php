<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Manufacturer;
use Illuminate\Support\Facades\Cache;

/**
 * タイヤサイズの表記ゆれを吸収する正規化と、「同じタイヤサイズの車種」抽出。
 *
 * bike_models.tire_size_front / tire_size_rear は同じサイズが複数表記で入っている
 * （"120/70ZR17" / "120/70ZR17M/C（58W）" / "120/70 ZR17" 等）。SQLでは突き合わせられないため、
 * PHP側で正規化してから一致判定する。全角マイナス "−" 等はデータ無しの意味なので null にする。
 */
final class TireSize
{
    /**
     * 生のタイヤ表記を比較用のコア文字列へ正規化する。突き合わせ不能・データ無しは null。
     */
    public static function normalize(?string $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }

        // 全角英数字・全角記号・全角空白を半角へ寄せる（（）／－ 等も半角化される）。
        $s = mb_convert_kana($s, 'as', 'UTF-8');

        // 括弧（半角・全角）とその中身を除去。例: （58W） (69W)
        $s = (string) preg_replace('/[（(][^）)]*[）)]/u', '', $s);

        // "M/C"（大小問わず）を除去。
        $s = (string) preg_replace('/m\/c/iu', '', $s);

        // 末尾のロードインデックス・速度記号（数字＋英字1文字）を除去。空白除去より前に行う
        // （空白を消してからだとリム径とLIの境界が失われ "120/80-14 58S" を誤って削るため）。
        $s = (string) preg_replace('/\s*\d{1,3}[A-Za-z]\s*$/u', '', $s);

        // 半角・全角の空白をすべて除去。
        $s = (string) preg_replace('/[\s\x{3000}]+/u', '', $s);

        // 英字は大文字へ。
        $s = strtoupper(trim($s));

        // 結果が2文字以下（"−"/"-"/"ー" 単体や短すぎる残骸）は無効。
        if (mb_strlen($s) <= 2) {
            return null;
        }

        return $s;
    }

    /**
     * 「同じタイヤサイズの車種」を返す。自車種の前サイズが正規化できなければ null（＝セクション非表示）。
     *
     * 返り値: ['mode' => 'both'|'front', 'items' => array<array{name:string, manufacturer:string, mfr_slug:string, model_slug:string}>]
     * - 前後一致が3件未満なら前輪のみ一致にフォールバック（mode='front'）。
     * - リンクは canonical URL（/bikes/{mfrSlug}/{modelSlug}）を組み立てるため mfr_slug/model_slug を持たせる。
     *   slug が欠ける車種はリンク不能なので除外する。
     * - 表示に必要な素の配列だけを Cache::remember へ保存（Eloquentモデルは入れない）。
     */
    public static function sameSizeModels(BikeModel $model): ?array
    {
        $selfFront = self::normalize($model->tire_size_front);
        if ($selfFront === null) {
            return null; // 自車種の前サイズ取得不可 → 呼び出し側でセクションごと非表示
        }
        $selfRear = self::normalize($model->tire_size_rear);
        $selfId = (int) $model->id;

        // 配列の形が変わったのでキー版を v2 に上げる（旧 v1 の配列を新コードが読んで壊れるのを防ぐ）。
        return Cache::remember("tire_same_size_v2:{$selfId}", 86400, function () use ($selfId, $selfFront, $selfRear) {
            // 全件取得は1回のみ（キャッシュミス時だけ実行）。列を明示。
            $all = BikeModel::query()
                ->select('id', 'name', 'slug', 'manufacturer_id', 'tire_size_front', 'tire_size_rear')
                ->get();

            $both = [];
            $frontOnly = [];
            foreach ($all as $m) {
                if ((int) $m->id === $selfId) {
                    continue; // 自分自身を除外
                }
                $nf = self::normalize($m->tire_size_front);
                if ($nf === null || $nf !== $selfFront) {
                    continue; // 前輪一致は必須
                }
                $frontOnly[] = $m;
                if ($selfRear !== null) {
                    $nr = self::normalize($m->tire_size_rear);
                    if ($nr !== null && $nr === $selfRear) {
                        $both[] = $m; // 前後とも一致
                    }
                }
            }

            $mode = count($both) >= 3 ? 'both' : 'front';
            $matched = $mode === 'both' ? $both : $frontOnly;
            if (empty($matched)) {
                return ['mode' => $mode, 'items' => []];
            }

            // 候補ぶんだけ在庫数（is_sold_out=0）とメーカー名を1クエリずつ取得。
            $ids = array_map(static fn ($m): int => (int) $m->id, $matched);
            $stock = Listing::query()
                ->whereIn('bike_model_id', $ids)
                ->where('is_sold_out', 0)
                ->selectRaw('bike_model_id, COUNT(*) as cnt')
                ->groupBy('bike_model_id')
                ->pluck('cnt', 'bike_model_id');

            $mfrIds = array_values(array_unique(array_map(static fn ($m): int => (int) $m->manufacturer_id, $matched)));
            $mfrs = Manufacturer::whereIn('id', $mfrIds)->get(['id', 'name', 'slug'])->keyBy('id');

            // 並び順: 在庫あり優先 → 車種名昇順。
            usort($matched, static function ($a, $b) use ($stock): int {
                $sa = (int) ($stock[$a->id] ?? 0) > 0 ? 1 : 0;
                $sb = (int) ($stock[$b->id] ?? 0) > 0 ? 1 : 0;
                if ($sa !== $sb) {
                    return $sb <=> $sa; // 在庫ありを前へ
                }

                return strcmp((string) $a->name, (string) $b->name);
            });

            // slug が欠ける車種（メーカーslug or 車種slug が null/空）はリンク不能なので除外。順序を保って最大12件。
            $items = [];
            foreach ($matched as $m) {
                $modelSlug = trim((string) ($m->slug ?? ''));
                $mfr = $mfrs[$m->manufacturer_id] ?? null;
                $mfrSlug = trim((string) ($mfr->slug ?? ''));
                if ($modelSlug === '' || $mfrSlug === '') {
                    continue;
                }
                $items[] = [
                    'name' => (string) $m->name,
                    'manufacturer' => (string) ($mfr->name ?? ''),
                    'mfr_slug' => $mfrSlug,
                    'model_slug' => $modelSlug,
                ];
                if (count($items) >= 12) {
                    break;
                }
            }

            return ['mode' => $mode, 'items' => $items];
        });
    }
}
