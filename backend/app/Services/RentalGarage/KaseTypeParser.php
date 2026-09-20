<?php

declare(strict_types=1);

namespace App\Services\RentalGarage;

/**
 * 加瀬倉庫（kase3535.com）物件ページHTMLから「対象物件自身の」区画種別コードを取り出す。
 *
 * ■ ページの構造（Next.js Flight ペイロード。self.__next_f のチャンク内に JSON が入り、
 *   引用符は \" にエスケープされている）
 *
 *   1) 対象物件（本物件）: {"object":{"id":"<id>", ... ,"types":[{"id":"cntn",...},{"id":"bike",...}]}}
 *      → "object": ラッパー付き。types が「その場に展開された」インライン配列。
 *   2) 周辺物件（罠）    : {"nearbyObjects":[{"id":"031307", ... ,"types":[{...}]}, ...]}
 *      → 近隣物件。各自 types を持つ。ここを拾ってはいけない。
 *   3) 種別の凡例（罠）  : {"storageTypes":[{"id":"cntn","name":"レンタルボックス（屋外型）"}, ...]}
 *      → サイト共通のフィルタ凡例。キーは "types" ではなく "storageTypes"。isAvailable を持たない。
 *   4) 参照形            : {"object":{"id":"<id>", ... ,"types":"$5:4:props:object:types"}}
 *      → Flight の重複排除参照。types が配列ではなく "$..." 文字列。
 *
 * ■ 特定方法（実HTMLで確認済み。報告書の「どこから／どう特定しているか」に対応）
 *   「対象物件自身の types」＝ "object":{"id":"<id>" ラッパーに属し、かつ
 *   "nearbyObjects" より前に現れる唯一のインライン "types":[{ 配列。
 *   周辺物件の types はすべて nearbyObjects の内側（＝それより後方）にあるため混ざらない。
 *   凡例は "storageTypes"、参照形は "types":"$..." で、いずれもインライン "types":[{ に一致しない。
 *
 * ■ 保持する情報
 *   返すのは type_code の配列のみ。name / isAvailable / availableCount / 画像URL は
 *   一切返さない（空き状況はリアルタイム情報のため扱わない）。
 */
final class KaseTypeParser
{
    /** 既知の区画種別コード。bike と bike-out は表示名が同じでも別物（id で判定）。 */
    public const KNOWN_CODES = ['cntn', 'bike', 'bike-out', 'trnk'];

    /**
     * 物件ページHTMLから対象物件自身の区画種別コード配列を返す。取れなければ空配列。
     *
     * @param  string  $html  物件ページの生HTML（Flight エスケープ済みのまま渡してよい）
     * @param  string  $objectId  物件ID（source_url 末尾の数字）
     * @param  array<int, string>|null  $allowed  許可コード（省略時は KNOWN_CODES）
     * @return array<int, string>
     */
    public static function parse(string $html, string $objectId, ?array $allowed = null): array
    {
        $allowed ??= self::KNOWN_CODES;

        $items = self::locateTargetTypes($html, $objectId);
        if ($items === null) {
            return [];
        }

        $codes = [];
        foreach ($items as $item) {
            // id（type_code）のみ採用。isAvailable / availableCount / name は捨てる。
            $code = is_array($item) ? ($item['id'] ?? null) : null;
            if (is_string($code) && in_array($code, $allowed, true)) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * 診断専用: 対象物件自身の types を [id => name] で返す（許可コードで絞らない）。
     *
     * ★ 種別ID と実際の表示名の対応を人手で確定するためだけに使う。
     *   DB には一切保存しない（本番の保存経路は parse() の type_code のみ）。
     *   name はリアルタイムの空き状況ではなく静的なラベル文字列なので診断表示に限り扱う。
     *
     * @return array<string, string> id => name（name が無ければ空文字）
     */
    public static function inspect(string $html, string $objectId): array
    {
        $items = self::locateTargetTypes($html, $objectId);
        if ($items === null) {
            return [];
        }

        $map = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = $item['id'] ?? null;
            if (! is_string($id) || $id === '') {
                continue;
            }
            $name = $item['name'] ?? '';
            $map[$id] = is_string($name) ? $name : '';
        }

        return $map;
    }

    /**
     * 対象物件自身の types 配列（デコード済みの生要素列）を返す。見つからなければ null。
     *
     * "object":{"id":"<id>" アンカーを順に走査し、直後のインライン types が
     * nearbyObjects より前にあるものを対象物件とみなす（参照形はインライン [{ に一致せず飛ばされる）。
     * parse() と inspect() で共有する。
     *
     * @return array<int, mixed>|null
     */
    private static function locateTargetTypes(string $html, string $objectId): ?array
    {
        // 数字IDのみ受け付ける（不正入力での誤マッチを防ぐ）。
        if (! preg_match('/^[0-9]+$/', $objectId)) {
            return null;
        }

        $q = '\\"'; // Flight ペイロード内の 1 つの二重引用符
        $anchor = $q.'object'.$q.':{'.$q.'id'.$q.':'.$q.$objectId.$q; // "object":{"id":"<id>"
        $inlineTypes = $q.'types'.$q.':[{';                             // "types":[{

        $nearbyPos = strpos($html, $q.'nearbyObjects'.$q);
        $bound = $nearbyPos === false ? strlen($html) : $nearbyPos;

        $from = 0;
        while (($objPos = strpos($html, $anchor, $from)) !== false) {
            $typesPos = strpos($html, $inlineTypes, $objPos);
            if ($typesPos !== false && $typesPos < $bound) {
                return self::decodeTypesAt($html, $typesPos + strlen($q.'types'.$q.':'));
            }
            $from = $objPos + strlen($anchor);
        }

        return null;
    }

    /**
     * $start（エスケープされた JSON 配列の '[' 位置）から配列を括弧対応で切り出し、
     * アンエスケープ→json_decode して要素配列（type オブジェクト列）を返す。壊れていれば空配列。
     *
     * @return array<int, mixed>
     */
    private static function decodeTypesAt(string $html, int $start): array
    {
        $len = strlen($html);
        $depth = 0;
        $inStr = false;
        $end = -1;

        for ($i = $start; $i < $len; $i++) {
            $c = $html[$i];
            // \" は文字列の開始／終了トグル（エスケープされた二重引用符）。
            if ($c === '\\' && $i + 1 < $len && $html[$i + 1] === '"') {
                $inStr = ! $inStr;
                $i++;

                continue;
            }
            if ($inStr) {
                continue;
            }
            if ($c === '[') {
                $depth++;
            } elseif ($c === ']') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        if ($end < 0) {
            return [];
        }

        $escaped = substr($html, $start, $end - $start + 1);
        // Flight のエスケープを戻す（\\ を先に、続けて \"）。
        $json = str_replace(['\\\\', '\\"'], ['\\', '"'], $escaped);

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
