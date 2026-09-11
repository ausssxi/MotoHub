<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 受信リクエストの User-Agent からクローラー（bot）を判定する唯一の置き場所。
 *
 * 目的は送客実数の集計から機械アクセスを除くこと。過検出より取りこぼしを避ける方針で、
 * 空の UA もスクリプト/クローラー由来とみなして bot 扱いにする。
 */
final class BotDetector
{
    /**
     * 一般的なクローラー・ライブラリの UA トークン。小文字化した UA に対して部分一致で判定する。
     */
    private const BOT_TOKENS = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
        'facebookexternalhit', 'facebot', 'embedly', 'quora link preview',
        'pinterest', 'redditbot', 'slackbot', 'telegrambot', 'twitterbot',
        'discordbot', 'whatsapp', 'line-poker', 'skypeuripreview',
        'google-inspectiontool', 'chrome-lighthouse', 'headlesschrome',
        'python-requests', 'python-httpx', 'httpx', 'aiohttp', 'go-http-client',
        'curl/', 'wget', 'okhttp', 'axios', 'guzzlehttp', 'scrapy',
        'phantomjs', 'apachebench', 'ahrefs', 'semrush', 'mj12', 'dotbot',
        'petalbot', 'bytespider', 'yandex', 'baidu', 'sogou', 'archive.org_bot',
    ];

    public static function isBot(?string $userAgent): bool
    {
        $ua = trim((string) $userAgent);
        if ($ua === '') {
            return true;
        }

        $ua = mb_strtolower($ua);
        foreach (self::BOT_TOKENS as $token) {
            if (str_contains($ua, $token)) {
                return true;
            }
        }

        return false;
    }
}
