<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ユーザー投稿型の店舗登録（承認制）。Webikeで拾えない整備・修理店を
 * 投稿 → Filamentで承認 → shops(source='user') へ正式登録する。
 * 防御は shop_acceptance_reports と同一（承認制・匿名OK・honeypot・throttle・ハッシュIP）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('shop_name', 100);
            $table->string('prefecture', 10);
            $table->string('city', 50);
            $table->string('address', 200)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('website_url', 200)->nullable();
            $table->json('service_tags')->nullable();      // 対応サービス（既存タグキー準拠）
            $table->json('acceptance_flags')->nullable();  // 他店購入車OK等4フラグ（任意）
            $table->text('comment')->nullable();
            $table->string('submitter_name', 50)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_hash', 64);
            $table->string('status', 20)->default('pending')->index(); // pending/approved/merged/rejected
            $table->foreignId('linked_shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_submissions');
    }
};
