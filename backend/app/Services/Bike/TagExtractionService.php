<?php

namespace App\Services\Bike;

use App\Models\Listing;
use App\Models\Tag;
use Illuminate\Support\Str;

class TagExtractionService
{
    /**
     * 車両データからタグを抽出し、中間テーブルに同期する
     */
    public function extractAndSyncTags(Listing $listing): void
    {
        // configから辞書を取得
        $dictionary = config('tags.dictionary', []);
        
        // 検索対象のテキスト（タイトル + 説明文）
        $textToSearch = $listing->title . ' ' . ($listing->description ?? '');
        
        $foundTags = [];
        $tagIdsToSync = [];

        foreach ($dictionary as $searchWord => $tagName) {
            // 大文字小文字を区別せずに検索
            if (mb_stripos($textToSearch, $searchWord) !== false) {
                if (!in_array($tagName, $foundTags)) {
                    $foundTags[] = $tagName;
                    
                    // DBにタグがなければ作成、あれば取得
                    $tag = Tag::firstOrCreate(
                        ['name' => $tagName],
                        ['slug' => $tagName] // ★修正: LaravelのStr::slugは日本語を消してしまうため、そのままタグ名を使用する
                    );
                    
                    $tagIdsToSync[] = $tag->id;
                }
            }
        }

        // 基本スペック（メーカーやタイプ）もタグにする場合
        if (!empty($listing->maker)) {
            $tag = Tag::firstOrCreate(
                ['name' => $listing->maker],
                ['slug' => $listing->maker] // ★修正: そのまま名前を使用する
            );
            $tagIdsToSync[] = $tag->id;
        }

        if (!empty($listing->category)) {
            $tag = Tag::firstOrCreate(
                ['name' => $listing->category],
                ['slug' => $listing->category] // ★修正: そのまま名前を使用する
            );
            $tagIdsToSync[] = $tag->id;
        }

        // 中間テーブルに保存（古いものは消え、新しいものに更新される）
        $listing->tags()->sync($tagIdsToSync);
    }
}