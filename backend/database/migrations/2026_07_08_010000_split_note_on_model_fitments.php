<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 備考を「公開用 note_public / 内部メモ note_internal」に分離する。
 *
 * 後方互換：既存 note 列は残す（旧1列CSVの受け皿＋ロールバック余地）。
 * 現在DBに在る note の値は内部メモとして退避し、公開露出を止める
 * （note_public は空で開始・公開noteは後日CSVで供給）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_fitments', function (Blueprint $table) {
            $table->text('note_public')->nullable()->after('note');
            $table->text('note_internal')->nullable()->after('note_public');
        });

        // 既存 note の中身は内部メモへ退避（公開ページから即座に消える）。note_public は空のまま。
        DB::table('model_fitments')
            ->whereNotNull('note')
            ->where('note', '!=', '')
            ->update(['note_internal' => DB::raw('note')]);
    }

    public function down(): void
    {
        // 追加2列のみ落とす（既存 note 列は元から在るので触らない）
        Schema::table('model_fitments', function (Blueprint $table) {
            $table->dropColumn(['note_public', 'note_internal']);
        });
    }
};
