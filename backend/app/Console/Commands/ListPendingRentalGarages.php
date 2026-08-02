<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RentalGarage;
use Illuminate\Console\Command;

/**
 * ユーザー投稿（source='user'）で未確認（is_verified=false）のレンタルガレージを一覧・集計する読み取り専用コマンド。
 * 商用施設の広告目的投稿を運営が後追い確認するための導線（管理画面ではなく件数確認用）。
 */
final class ListPendingRentalGarages extends Command
{
    protected $signature = 'rental_garage:pending {--limit=30 : 一覧表示する最大件数}';

    protected $description = 'ユーザー投稿かつ未確認(source=user, is_verified=false)のレンタルガレージを集計・一覧';

    public function handle(): int
    {
        $base = RentalGarage::query()->where('source', 'user')->where('is_verified', false);

        $total = (clone $base)->count();
        $active = (clone $base)->where('is_active', true)->count();
        $inactive = (clone $base)->where('is_active', false)->count();

        $this->info("ユーザー投稿・未確認: {$total} 件（公開中 {$active} / 非公開 {$inactive}）");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $rows = (clone $base)->latest('id')->limit($limit)->get([
            'id', 'name', 'operator', 'prefecture', 'is_active', 'submitted_by', 'created_at',
        ]);

        $this->table(
            ['ID', '施設名', '運営', '都道府県', '公開', '投稿者ID', '投稿日時'],
            $rows->map(fn (RentalGarage $g): array => [
                $g->id,
                mb_strimwidth((string) $g->name, 0, 24, '…'),
                mb_strimwidth((string) ($g->operator ?? '-'), 0, 16, '…'),
                $g->prefecture ?? '-',
                $g->is_active ? '公開' : '非公開',
                $g->submitted_by ?? '-',
                (string) $g->created_at,
            ])->all()
        );

        return self::SUCCESS;
    }
}
