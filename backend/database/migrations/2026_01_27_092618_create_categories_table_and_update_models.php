<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. categories テーブル作成
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('カテゴリ名（ネイキッド等）');
            $table->string('slug')->nullable()->comment('URL用スラッグ（naked等）');
            
            // ✨ 修正: 他のテーブルに合わせてURLとローカルパスを分離
            $table->string('icon_url')->nullable()->comment('外部アイコン画像のURL');
            $table->string('local_icon_path')->nullable()->comment('サーバー内に保存されたアイコンのパス');
            
            $table->integer('sort_order')->default(999)->comment('表示順');
            
            // タイムスタンプの自動更新設定とコメント
            $table->timestamp('created_at')->useCurrent()->comment('作成日時');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('更新日時');
        });

        // 2. bike_models に category_id を追加
        Schema::table('bike_models', function (Blueprint $table) {
            // 既存の category カラムの後に id を追加
            $table->foreignId('category_id')
                  ->nullable()
                  ->after('category')
                  ->constrained('categories')
                  ->nullOnDelete()
                  ->comment('カテゴリID');
        });
    }

    public function down(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
        Schema::dropIfExists('categories');
    }
};