<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\PushSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendNewStockPush extends Command
{
    protected $signature = 'push:new-stock {--dry-run : 実際に送信せずに内容だけ表示}';
    protected $description = '新着入荷があった車種の購読者にプッシュ通知を送信します';

    public function handle(): void
    {
        $this->info('新着入荷プッシュ通知の送信を開始します...');

        $yesterday = now()->subDay()->startOfDay();
        $todayStart = now()->startOfDay();

        // 車種別の新着台数をDBで集計（メモリ節約）
        $countsByModel = Listing::where('is_sold_out', false)
            ->whereBetween('created_at', [$yesterday, $todayStart])
            ->whereNotNull('bike_model_id')
            ->groupBy('bike_model_id')
            ->pluck(DB::raw('COUNT(*) as cnt'), 'bike_model_id');

        if ($countsByModel->isEmpty()) {
            $this->info('昨日の新着入荷はありませんでした。');
            return;
        }

        $modelIds = $countsByModel->keys();
        $this->info("新着がある車種: {$modelIds->count()}件");

        // 該当車種を購読中のサブスクリプションを取得
        $subscriptions = PushSubscription::with('bikeModel.manufacturer')
            ->whereIn('bike_model_id', $modelIds)
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('通知対象の購読者がいません。');
            return;
        }

        $this->info("通知対象: {$subscriptions->count()}件のサブスクリプション");

        if ($this->option('dry-run')) {
            $this->info('--- Dry Run ---');
            foreach ($subscriptions->groupBy('bike_model_id') as $modelId => $subs) {
                $bikeModel = $subs->first()->bikeModel;
                $count = $countsByModel[$modelId] ?? 0;
                $name = $bikeModel?->name ?? "車種ID: {$modelId}";
                $maker = $bikeModel?->manufacturer?->name ?? '';
                $this->line("  {$name} ({$maker}): {$count}台入荷 → {$subs->count()}人に通知");
                $payload = $this->buildPayload($bikeModel, $count);
                $this->line("    Title: {$payload['title']}");
                $this->line("    Body:  {$payload['body']}");
                $this->line("    URL:   {$payload['url']}");
            }
            return;
        }

        // WebPush クライアント初期化
        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => config('webpush.vapid.subject'),
                'publicKey'  => config('webpush.vapid.public_key'),
                'privateKey' => config('webpush.vapid.private_key'),
            ],
        ]);

        $sent = 0;
        $failed = 0;
        $expired = [];

        foreach ($subscriptions as $sub) {
            $count = $countsByModel[$sub->bike_model_id] ?? 0;
            $payload = $this->buildPayload($sub->bikeModel, $count);

            $webPush->queueNotification(
                Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'keys'            => [
                        'p256dh' => $sub->p256dh,
                        'auth'   => $sub->auth,
                    ],
                    'contentEncoding' => 'aesgcm',
                ]),
                json_encode($payload),
            );
        }

        // 一括送信
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } else {
                $failed++;
                $endpoint = $report->getEndpoint();
                $reason = $report->getReason();

                // 410 Gone / 404 は期限切れのサブスクリプション → 削除対象
                $statusCode = $report->getResponse()?->getStatusCode();
                if (in_array($statusCode, [404, 410], true)) {
                    $expired[] = $endpoint;
                }

                Log::warning("Push通知送信失敗: {$reason}", [
                    'endpoint' => substr($endpoint, 0, 80),
                    'status'   => $statusCode,
                ]);
            }
        }

        // 期限切れサブスクリプションの削除
        if (!empty($expired)) {
            $hashes = array_map(fn (string $ep) => hash('sha256', $ep), $expired);
            $deleted = PushSubscription::whereIn('endpoint_hash', $hashes)->delete();
            $this->info("期限切れサブスクリプションを{$deleted}件削除しました。");
        }

        $this->info("送信完了: 成功 {$sent}件 / 失敗 {$failed}件");
    }

    /**
     * 通知ペイロードを構築
     */
    private function buildPayload(?BikeModel $bikeModel, int $count): array
    {
        $name = $bikeModel?->name ?? '不明な車種';
        $maker = $bikeModel?->manufacturer?->name ?? '';
        $url = ($bikeModel?->manufacturer?->slug && $bikeModel?->slug)
            ? 'https://www.motohub.jp/bikes/' . $bikeModel->manufacturer->slug . '/' . $bikeModel->slug
            : ($bikeModel ? ('https://www.motohub.jp' . $bikeModel->seo_url) : 'https://www.motohub.jp/bikes/search');

        return [
            'title' => "📦 {$name} 新着入荷！",
            'body'  => "{$maker} {$name} に {$count}台の新着が入荷しました。今すぐチェック！",
            'url'   => $url,
            'icon'  => 'https://www.motohub.jp/favicon-96x96.png',
            'badge' => 'https://www.motohub.jp/favicon-96x96.png',
            'tag'   => 'new-stock-' . ($bikeModel?->id ?? 0),
        ];
    }
}
