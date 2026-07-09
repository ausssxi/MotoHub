<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * コメント（主観・一言テキスト）の公開状態を、事実系フラグの承認状態（is_approved）から
 * 分離する。コメントは即反映（comment_approved=true）、事実系フラグは従来どおり承認維持
 * （is_approved）。同一レコードで両方を持つための列分離（棚卸し §4 案a・内田さん承認済み）。
 *
 * ⚠️ is_approved の default・意味は一切変更しない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_acceptance_reports', function (Blueprint $table) {
            $table->boolean('comment_approved')->default(false)->after('is_approved');
        });

        // 既存データ移行: すでに承認済み(is_approved=true)のレコードのコメントは公開済み扱い。
        // これで今表示されているコメントが消えない。
        DB::table('shop_acceptance_reports')
            ->where('is_approved', true)
            ->update(['comment_approved' => true]);
    }

    public function down(): void
    {
        Schema::table('shop_acceptance_reports', function (Blueprint $table) {
            $table->dropColumn('comment_approved');
        });
    }
};
