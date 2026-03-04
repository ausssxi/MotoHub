<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_features', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 150)->unique()->comment('URL用スラッグ (例: commute-125cc)');
            $table->string('title', 255)->comment('ページH1タイトル');
            $table->string('meta_description', 500)->comment('SEO用 meta description');
            $table->text('content_header')->comment('ページ上部のリードコピー (HTML可)');
            $table->json('search_conditions')->comment('ListingSearchServiceに渡すfilters配列');
            $table->string('keyword', 255)->nullable()->comment('全文検索キーワード (search第1引数)');
            $table->string('prefecture', 50)->nullable()->comment('地域絞り込み (search第2引数)');
            $table->string('sort', 50)->default('latest')->comment('デフォルトのソート順');
            $table->boolean('is_active')->default(true)->comment('公開フラグ');
            $table->unsignedInteger('sort_order')->default(0)->comment('一覧での表示順 (昇順)');
            $table->string('icon', 50)->nullable()->comment('Lucideアイコン名');
            $table->string('color', 100)->nullable()->comment('カード用グラデーションCSSクラス');
            $table->timestamp('created_at')->useCurrent()->comment('作成日時');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('更新日時');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_features');
    }
};
