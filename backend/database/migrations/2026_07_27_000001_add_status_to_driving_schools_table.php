<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * driving_schools に status（公開判定）カラムを追加する。
 *
 * これまで verified_at が「最後に確認した日」と「公開してよいか」を兼ねており、
 * 非公開化のたびに verified_at を NULL に戻す必要があって確認日の記録が失われていた。
 * status を分離することで「二輪を習えるか」で公開判定し、非公開校も確認日つきで追跡する。
 *
 * status の値:
 *  - open            … 通常営業・二輪教習を受付中。公開対象（verified_at 非NULL かつ status=open のみ公開）。
 *  - nirin_suspended … 二輪教習を一時停止／見合せ中。校自体は存続。再開したら open に戻す候補。非公開。
 *  - closed          … 廃業・営業終了。恒久的に非公開。再開は想定しない。
 *
 * verified_at は NULL 許容のまま（未確認の校がありうる）。今後は「確認したら入れて、
 * 二度と NULL に戻さない」運用とし、公開可否は status で表現する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driving_schools', function (Blueprint $table) {
            $table->string('status', 20)->default('open')->after('verified_at'); // open / nirin_suspended / closed
            $table->index('status', 'driving_schools_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('driving_schools', function (Blueprint $table) {
            $table->dropIndex('driving_schools_status_idx');
            $table->dropColumn('status');
        });
    }
};
