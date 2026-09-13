<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Poi;

/**
 * POI（GS/コンビニ/洗車場）の表示名の唯一の解決ロジック。
 *
 * 以前は PoiAreaController だけが持っていたため、地図JS（poi-name.js）が別実装で
 * item.name をそのまま出し、詳細ページ（ENEOS（荏田東二丁目）等）と食い違っていた。
 * このクラスに集約し、詳細ページ・API（display 属性）の双方が同じ結果を返すようにする。
 *
 * 解決順序（現行踏襲・1文字も変えない）:
 *   1) name が非空   → name（ブランドトークンと完全一致なら正規表記に置換）
 *   2) brand が非空  → gasOperatorLabel / cvsOperatorLabel の正規化表記
 *   3) address の町名 → 「{種別名}（{町名}）」（洗車場は設備ラベル）
 *   4) それ以外       → address →「名称不明」（displayName のフォールバック）
 */
final class PoiDisplayResolver
{
    /** @var array<string, array<string, string>>|null  type => (正規化トークン => 正規化ブランド名) */
    private ?array $exactBrandTokens = null;

    /**
     * 表示名の決定。洗車場で name も brand も無い行は、住所の代わりに設備ラベルを使う。
     *
     * $gasBrand を渡すと、設備ラベルに落ちる（名前を持たない）洗車場に限り近接GSのブランドを併記する。
     * 名前を持つ施設は displayName() をそのまま返すので、$gasBrand を渡してもラベルは一切変わらない。
     */
    public function resolve(Poi $poi, string $type, string $prefecture, string $city, ?string $gasBrand = null): string
    {
        $display = $this->displayName($poi);

        if ($type === 'car_wash' && filled($poi->address) && $display === trim((string) $poi->address)) {
            return $this->carWashLabel($poi, $prefecture, $city, $gasBrand);
        }

        if ($type === 'gas_station' || $type === 'convenience_store') {
            return $this->brandHeading($poi, $prefecture, $city);
        }

        return $display;
    }

    /**
     * 表示名フォールバック: name → brand（種別ごとに正規化）→ address →「名称不明」。
     * name は一切いじらない。素のブランド名そのもの（完全一致）だけ正規化名へ置換する。
     */
    public function displayName(Poi $poi): string
    {
        $name = trim((string) ($poi->name ?? ''));
        if ($name !== '') {
            return $this->exactBrandLabel($poi) ?? $name;
        }

        $brandLabel = $this->normalizedBrand($poi);
        if ($brandLabel !== null && $brandLabel !== '') {
            return $brandLabel;
        }

        $address = trim((string) ($poi->address ?? ''));
        if ($address !== '') {
            return $address;
        }

        return '名称不明';
    }

    /**
     * GS/コンビニ詳細の見出し。ブランドの表記ゆれを統一しつつ具体的店名は温存する。
     *   - name が具体的店名 → そのまま（name 内は一切いじらない）
     *   - name が素のブランド名そのもの（config patterns と【完全一致】）→ 正規化名へ
     *   - name が無い → brand を正規化名に
     *   正規化名になった見出しだけ townPart() の町名を併記して同一市内の重複を解消する（例: ENEOS（荏田東二丁目））。
     */
    public function brandHeading(Poi $poi, string $prefecture, string $city): string
    {
        $name = trim((string) ($poi->name ?? ''));
        if ($name !== '') {
            $exact = $this->exactBrandLabel($poi);
            if ($exact === null) {
                return $name; // 具体的店名 → 温存
            }
            $base = $exact; // 素のブランド名（完全一致）→ 正規化して町名併記へ
        } else {
            $base = $this->normalizedBrand($poi);
            if ($base === null || $base === '') {
                return $this->displayName($poi);
            }
        }

        $town = AddressFormatter::townPart($prefecture, $city, $poi->address);

        return $town !== '' ? $base.'（'.$town.'）' : $base;
    }

    /**
     * brand の正規化表示名（表記ゆれ統一の単一入口）。種別ごとに config 駆動の分類を通す。
     * 分類できない独立系は生の屋号を返す。exclude/空は null。
     */
    public function normalizedBrand(Poi $poi): ?string
    {
        $brand = trim((string) ($poi->brand ?? ''));
        if ($brand === '') {
            return null;
        }

        return match ($poi->type) {
            'gas_station' => Poi::gasOperatorLabel($brand, Poi::gasBrand($brand)) ?? $brand,
            'convenience_store' => Poi::cvsOperatorLabel($brand, Poi::cvsBrand($brand)) ?? $brand,
            default => $brand,
        };
    }

    /**
     * name が「素のブランド名そのもの」のときだけ、その正規化表示名を返す（そうでなければ null）。
     * name を ShopNameNormalizer で正規化し、config の brand トークンと【完全一致】するか。
     * 部分一致では絶対に置換しない（「エネオス安波給油所」は触らない）。GS/コンビニのみ対象。
     */
    public function exactBrandLabel(Poi $poi): ?string
    {
        if ($poi->type !== 'gas_station' && $poi->type !== 'convenience_store') {
            return null;
        }
        $name = trim((string) ($poi->name ?? ''));
        if ($name === '') {
            return null;
        }

        $tokens = $this->exactBrandTokens()[$poi->type] ?? [];

        return $tokens[ShopNameNormalizer::normalize($name)] ?? null;
    }

    /**
     * 洗車場の設備ラベル。self_service/automated から「コイン洗車場/セルフ洗車場/洗車機/洗車場」を決め、
     * 町名・近接GSブランド（あれば「◯◯併設」）を括弧に併記する。
     */
    public function carWashLabel(Poi $poi, string $prefecture, string $city, ?string $gasBrand = null): string
    {
        $yes = static fn ($v): bool => in_array(strtolower(trim((string) ($v ?? ''))), ['yes', 'only'], true);
        $self = $yes($poi->self_service);
        $auto = $yes($poi->automated);

        if ($self && $auto) {
            $label = 'コイン洗車場';
        } elseif ($self) {
            $label = 'セルフ洗車場';
        } elseif ($auto) {
            $label = '洗車機';
        } else {
            $label = '洗車場';
        }

        $town = AddressFormatter::townPart($prefecture, $city, $poi->address);

        $parts = [];
        if ($town !== '') {
            $parts[] = $town;
        }
        if (filled($gasBrand)) {
            $parts[] = $gasBrand.'併設';
        }

        return $parts !== [] ? $label.'（'.implode('・', $parts).'）' : $label;
    }

    /**
     * 完全一致用の「正規化トークン → 正規化ブランド名」表を config から構築（1インスタンス1回・メモ化）。
     * cosmo / ja-ss は patterns が空で gasBrand() のガード判定に依存するため、そのキーワードを補う。
     *
     * @return array<string, array<string, string>>
     */
    private function exactBrandTokens(): array
    {
        if ($this->exactBrandTokens !== null) {
            return $this->exactBrandTokens;
        }

        $build = static function (string $configKey): array {
            $map = [];
            foreach ((array) config($configKey, []) as $def) {
                $name = $def['name'] ?? null;
                if (! is_string($name) || $name === '') {
                    continue;
                }
                foreach (array_merge($def['patterns'] ?? [], [$name]) as $token) {
                    $k = ShopNameNormalizer::normalize((string) $token);
                    if ($k !== '') {
                        $map[$k] = $name;
                    }
                }
            }

            return $map;
        };

        $gas = $build('gas.brands');
        $cosmo = (string) config('gas.brands.cosmo.name', 'コスモ石油');
        $jass = (string) config('gas.brands.ja-ss.name', 'JA-SS');
        foreach (['コスモ' => $cosmo, 'cosmo' => $cosmo, 'ja' => $jass, 'ja-ss' => $jass, 'jass' => $jass, '全農' => $jass, '農協' => $jass] as $tok => $canonical) {
            $gas[ShopNameNormalizer::normalize($tok)] = $canonical;
        }

        return $this->exactBrandTokens = [
            'gas_station' => $gas,
            'convenience_store' => $build('convenience.brands'),
        ];
    }
}
