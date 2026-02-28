<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineNotificationService
{
    private string $channelToken;
    private string $apiBaseUrl = 'https://api.line.me/v2/bot';

    public function __construct()
    {
        $this->channelToken = config('services.line.messaging_token')
            ?? env('LINE_MESSAGING_CHANNEL_TOKEN', '');
    }

    /**
     * 値下げアラートをLINEで送信
     *
     * @param User   $user      通知先ユーザー
     * @param object $listing   バイク情報（name, id, images 等）
     * @param float  $oldPrice  旧価格（万円）
     * @param float  $newPrice  新価格（万円）
     */
    public function sendPriceDropAlert(User $user, $listing, float $oldPrice, float $newPrice): bool
    {
        if (!$user->line_id) {
            Log::warning("LINE通知スキップ: User#{$user->id} にline_idがありません");
            return false;
        }

        $priceDiff = $oldPrice - $newPrice;
        $detailUrl = url("/bikes/{$listing->id}");
        $imageUrl = !empty($listing->images[0]) ? $listing->images[0] : null;

        // Flex Messageでリッチな通知を送信
        $flexMessage = $this->buildPriceDropFlexMessage(
            $listing->name,
            $oldPrice,
            $newPrice,
            $priceDiff,
            $detailUrl,
            $imageUrl
        );

        return $this->pushMessage($user->line_id, [
            [
                'type'     => 'flex',
                'altText'  => "【値下げ】{$listing->name} が {$priceDiff}万円値下げされました！",
                'contents' => $flexMessage,
            ]
        ]);
    }

    /**
     * シンプルなテキストメッセージを送信
     */
    public function sendTextMessage(User $user, string $text): bool
    {
        if (!$user->line_id) {
            return false;
        }

        return $this->pushMessage($user->line_id, [
            [
                'type' => 'text',
                'text' => $text,
            ]
        ]);
    }

    /**
     * LINE Messaging API でプッシュメッセージ送信
     */
    private function pushMessage(string $lineId, array $messages): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->channelToken}",
                'Content-Type'  => 'application/json',
            ])->post("{$this->apiBaseUrl}/message/push", [
                'to'       => $lineId,
                'messages' => $messages,
            ]);

            if ($response->successful()) {
                Log::info("LINE通知送信成功: lineId={$lineId}");
                return true;
            }

            Log::error("LINE通知送信失敗: lineId={$lineId}", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error("LINE通知例外: lineId={$lineId}", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 値下げアラート用のFlex Messageを構築
     */
    private function buildPriceDropFlexMessage(
        string $bikeName,
        float $oldPrice,
        float $newPrice,
        float $priceDiff,
        string $detailUrl,
        ?string $imageUrl
    ): array {
        $body = [
            'type'     => 'bubble',
            'size'     => 'mega',
            'header'   => [
                'type'            => 'box',
                'layout'          => 'vertical',
                'backgroundColor' => '#FF3B30',
                'paddingAll'      => 'lg',
                'contents'        => [
                    [
                        'type'   => 'text',
                        'text'   => '🔔 値下げアラート',
                        'color'  => '#ffffff',
                        'size'   => 'sm',
                        'weight' => 'bold',
                    ],
                ],
            ],
            'body' => [
                'type'     => 'box',
                'layout'   => 'vertical',
                'spacing'  => 'md',
                'contents' => [
                    // 車両名
                    [
                        'type'   => 'text',
                        'text'   => $bikeName,
                        'weight' => 'bold',
                        'size'   => 'lg',
                        'wrap'   => true,
                    ],
                    // 区切り線
                    [
                        'type'  => 'separator',
                        'margin' => 'lg',
                    ],
                    // 価格情報
                    [
                        'type'    => 'box',
                        'layout'  => 'vertical',
                        'margin'  => 'lg',
                        'spacing' => 'sm',
                        'contents' => [
                            [
                                'type'     => 'box',
                                'layout'   => 'horizontal',
                                'contents' => [
                                    [
                                        'type'  => 'text',
                                        'text'  => '変更前',
                                        'size'  => 'sm',
                                        'color' => '#999999',
                                        'flex'  => 2,
                                    ],
                                    [
                                        'type'       => 'text',
                                        'text'       => "{$oldPrice}万円",
                                        'size'       => 'sm',
                                        'color'      => '#999999',
                                        'align'      => 'end',
                                        'flex'       => 3,
                                        'decoration' => 'line-through',
                                    ],
                                ],
                            ],
                            [
                                'type'     => 'box',
                                'layout'   => 'horizontal',
                                'contents' => [
                                    [
                                        'type'   => 'text',
                                        'text'   => '変更後',
                                        'size'   => 'sm',
                                        'color'  => '#999999',
                                        'flex'   => 2,
                                    ],
                                    [
                                        'type'   => 'text',
                                        'text'   => "{$newPrice}万円",
                                        'size'   => 'xl',
                                        'color'  => '#FF3B30',
                                        'weight' => 'bold',
                                        'align'  => 'end',
                                        'flex'   => 3,
                                    ],
                                ],
                            ],
                            [
                                'type'     => 'box',
                                'layout'   => 'horizontal',
                                'margin'   => 'md',
                                'contents' => [
                                    [
                                        'type'            => 'text',
                                        'text'            => "🎉 {$priceDiff}万円 値下げ！",
                                        'size'            => 'md',
                                        'color'           => '#FF3B30',
                                        'weight'          => 'bold',
                                        'align'           => 'center',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'footer' => [
                'type'     => 'box',
                'layout'   => 'vertical',
                'spacing'  => 'sm',
                'contents' => [
                    [
                        'type'    => 'button',
                        'style'   => 'primary',
                        'color'   => '#FF3B30',
                        'height'  => 'md',
                        'action'  => [
                            'type'  => 'uri',
                            'label' => '車両を確認する',
                            'uri'   => $detailUrl,
                        ],
                    ],
                    [
                        'type'    => 'button',
                        'style'   => 'secondary',
                        'height'  => 'sm',
                        'action'  => [
                            'type'  => 'uri',
                            'label' => 'お気に入り一覧',
                            'uri'   => url('/wishlist'),
                        ],
                    ],
                ],
            ],
        ];

        // 画像がある場合はヒーロー画像を追加
        if ($imageUrl) {
            $body['hero'] = [
                'type'        => 'image',
                'url'         => $imageUrl,
                'size'        => 'full',
                'aspectRatio' => '4:3',
                'aspectMode'  => 'cover',
                'action'      => [
                    'type' => 'uri',
                    'uri'  => $detailUrl,
                ],
            ];
        }

        return $body;
    }
}