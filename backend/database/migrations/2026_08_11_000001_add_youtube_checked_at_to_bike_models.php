<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * youtube:refresh（RefreshBikeYouTube）の並び順に使う「最後にYouTubeへ問い合わせた日時」。
 *
 * 同コマンドの対象は「DB動画が無い在庫あり車種」で、定義上 bike_model_videos の行を持たない。
 * そのため updated_at を並び順に使えず、クォータ超過で打ち切られたときにどこまで
 * 問い合わせ済みかを持てなかった。この列に問い合わせ日時を刻み、NULL（未問い合わせ）を
 * 先頭・次に古い順で回すことで、打ち切り後の実行が続きから再開できるようにする。
 *
 * NULL 許容にしているのは、既存行を「未問い合わせ」として扱うため。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            $table->timestamp('youtube_checked_at')
                ->nullable()
                ->comment('最後にYouTube APIへ問い合わせた日時。NULL=未問い合わせ');

            // 未問い合わせ→古い順で取り出すための索引
            $table->index('youtube_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            $table->dropIndex(['youtube_checked_at']);
            $table->dropColumn('youtube_checked_at');
        });
    }
};
