<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixMissingManufacturerIds extends Command
{
    /**
     * コマンドの呼び出し名
     */
    protected $signature = 'bikes:fix-manufacturer-ids';

    /**
     * コマンドの説明
     */
    protected $description = 'メーカーIDが欠落している車両データに、車種マスタからメーカーIDを安全に補完します';

    public function handle()
    {
        $this->info('🔍 メーカーIDの補完処理を開始します...');

        // 1. まず、対象となるデータが何件あるかを確認する
        $targetCount = DB::table('listings')
            ->whereNull('manufacturer_id')
            ->orWhere('manufacturer_id', 0)
            ->count();

        if ($targetCount === 0) {
            $this->info('✅ 補完が必要なデータはありませんでした。');
            return;
        }

        $this->warn("⚠️ 【{$targetCount}件】 の車両データでメーカーIDが欠落しています。");

        // 2. 実行前の最終確認（間違えて実行するのを防ぐ）
        if (!$this->confirm('これらにメーカーIDを自動補完します。実行してもよろしいですか？')) {
            $this->info('🛑 処理をキャンセルしました。');
            return;
        }

        // 3. 安全装置（トランザクション）を開始
        DB::beginTransaction();
        try {
            // 安全なSQL実行
            $affectedRows = DB::statement("
                UPDATE listings l
                INNER JOIN bike_models bm ON l.bike_model_id = bm.id
                SET l.manufacturer_id = bm.manufacturer_id
                WHERE l.manufacturer_id IS NULL OR l.manufacturer_id = 0
            ");

            // 処理が成功したら確定させる
            DB::commit();
            $this->info("🎉 成功: {$targetCount}件のデータにメーカーIDを補完しました！");
            
        } catch (\Exception $e) {
            // もし何かエラーが起きたら、処理を行う前の状態に巻き戻す
            DB::rollBack();
            $this->error('❌ エラーが発生したため、データベースを元の状態に戻しました。');
            $this->error('詳細: ' . $e->getMessage());
        }
    }
}