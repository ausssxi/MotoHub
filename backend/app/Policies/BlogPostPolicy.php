<?php

namespace App\Policies;

use App\Models\BlogPost;
use App\Models\User;

class BlogPostPolicy
{
    /**
     * 記事の編集権限: admin は全記事可、それ以外は自分の記事のみ
     */
    public function update(User $user, BlogPost $post): bool
    {
        return $user->role === 'admin' || $user->id === $post->author_id;
    }

    /**
     * 記事の削除権限: admin は全記事可、それ以外は自分の下書きのみ
     */
    public function delete(User $user, BlogPost $post): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->id === $post->author_id && $post->status === 'draft';
    }

    /**
     * 公開操作は admin のみ
     */
    public function publish(User $user, BlogPost $post): bool
    {
        return $user->role === 'admin';
    }
}
