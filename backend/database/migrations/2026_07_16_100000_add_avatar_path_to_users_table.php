<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ユーザーがアップロードした自前アバターの保存パス（public ディスク上の相対パス）。
 * 既存の `avatar`（Google/LINE の外部URL）とは別カラムにして OAuth アバターを壊さない。
 * 表示は User::avatarUrl アクセサで avatar_path（自前・優先）→ avatar（外部URL）→ null の順に解決。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_path')->nullable()->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('avatar_path');
        });
    }
};
