<?php

declare(strict_types=1);

namespace App\Services\Blog;

final class ShortcodeService
{
    /**
     * HTML内の[riders-map ...]ショートコードを検出し、マップ用divに置換する。
     *
     * @return array{html: string, hasMap: bool}
     */
    public function processShortcodes(string $html): array
    {
        $hasMap = false;

        // CommonMarkが<p>タグで囲む場合と囲まない場合の両方に対応
        $pattern = '/(?:<p>\s*)?\[riders-map\s+([^\]]+)\](?:\s*<\/p>)?/';

        $html = preg_replace_callback($pattern, function (array $matches) use (&$hasMap) {
            $hasMap = true;
            $params = $this->parseParams($matches[1]);

            $lat = $params['lat'] ?? '35.681236';
            $lng = $params['lng'] ?? '139.767125';
            $zoom = $params['zoom'] ?? '12';
            $height = $params['height'] ?? '400';
            $layers = $params['layers'] ?? 'all';
            $route = $params['route'] ?? '';

            $attrs = sprintf(
                'data-lat="%s" data-lng="%s" data-zoom="%s" data-layers="%s"',
                e($lat),
                e($lng),
                e($zoom),
                e($layers),
            );

            if ($route !== '') {
                $attrs .= sprintf(' data-route="%s"', e($route));
            }

            return sprintf(
                '<div class="riders-map-embed" %s style="height:%dpx;width:100%%;min-height:300px;border-radius:0.75rem;overflow:hidden;margin:1.5rem 0;"></div>',
                $attrs,
                max((int) $height, 300),
            );
        }, $html) ?? $html;

        return ['html' => $html, 'hasMap' => $hasMap];
    }

    /**
     * "key=value key2=value2" 形式のパラメータ文字列をパースする。
     *
     * @return array<string, string>
     */
    private function parseParams(string $raw): array
    {
        $params = [];
        // key=value（値はクォート有無どちらも対応）
        preg_match_all('/(\w+)=["\']?([^"\'\s]+)["\']?/', $raw, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $params[$match[1]] = $match[2];
        }

        return $params;
    }
}
