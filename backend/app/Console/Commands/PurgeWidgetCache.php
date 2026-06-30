<?php

namespace App\Console\Commands;

use App\Services\CloudflareCacheService;
use Illuminate\Console\Command;

/**
 * 埋め込みウィジェット用スクリプト(price.js)の Cloudflare エッジキャッシュをパージする。
 *
 * price.js は外部サイトが ?v= 無し(バージョン文字列なし)で読み込むため、デプロイ
 * (git pull origin main)後にエッジを明示パージしないと、最大 max-age=3600 ぶん
 * Cloudflare が旧版を返し続ける。git pull で price.js を更新したら本コマンドを実行する:
 *
 *   php artisan widget:purge-cache
 *
 * デフォルトのパージ対象 URL は、埋め込みコード(widget.blade.php)と price.js が
 * 実際に出力する URL と完全一致させる必要があるためハードコードしている。
 * apex 等を追加でパージしたい場合は --url= で渡す。
 */
class PurgeWidgetCache extends Command
{
    protected $signature = 'widget:purge-cache
                            {--url=* : 追加でパージするURL(既定の price.js に加えて)}';

    protected $description = '埋め込みウィジェット(price.js)のCloudflareエッジキャッシュをパージ';

    /**
     * 埋め込みコードが実際に読み込む price.js の URL(www 正規ドメイン)。
     */
    private const WIDGET_SCRIPT_URL = 'https://www.motohub.jp/widget/price.js';

    public function handle(CloudflareCacheService $cache): int
    {
        $urls = array_values(array_unique(array_merge(
            [self::WIDGET_SCRIPT_URL],
            $this->option('url'),
        )));

        $this->info(count($urls).'件のURLをパージしています...');
        foreach ($urls as $url) {
            $this->line("  {$url}");
        }

        if ($cache->purgeUrls($urls)) {
            $this->info('Cloudflareキャッシュをパージしました。修正は即時エッジに反映されます。');

            return self::SUCCESS;
        }

        $this->error('キャッシュパージに失敗しました。Cloudflare設定(CLOUDFLARE_ZONE_ID / CLOUDFLARE_API_TOKEN)を確認してください。');
        $this->warn('※ パージ未実施でも、エッジTTL(max-age=3600)満了後に自然反映されます。');

        return self::FAILURE;
    }
}
