<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    protected $signature = 'blog:publish-scheduled';

    protected $description = '予約投稿の公開: scheduled状態で公開日時を過ぎた記事をpublishedに変更';

    public function handle(): int
    {
        $count = BlogPost::scheduled()->update(['status' => 'published']);

        if ($count > 0) {
            $this->info("{$count}件の記事を公開しました。");
        }

        return self::SUCCESS;
    }
}
