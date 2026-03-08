<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use App\Models\ParkingReview;
use Illuminate\Console\Command;

class SeedParkingReviews extends Command
{
    protected $signature = 'parking:seed-reviews
        {--count=20 : 投稿するレビュー数}';

    protected $description = '人気エリアの駐車場にシードレビューを自動生成します';

    private const TEMPLATES = [
        ['rating' => 5, 'body' => '駅近で見つけやすい。屋根付きで雨の日も安心。', 'nickname' => 'ツーリングライダー'],
        ['rating' => 4, 'body' => '料金は普通だけど、24時間出し入れできるのが便利。', 'nickname' => 'CB乗り'],
        ['rating' => 3, 'body' => 'ちょっと狭めだけど、この辺で他にないので使ってます。', 'nickname' => '週末ライダー'],
        ['rating' => 4, 'body' => '大型バイクも問題なく停められた。出入口が少し狭いので注意。', 'nickname' => 'ハーレー乗り'],
        ['rating' => 5, 'body' => '無料で使えるのがありがたい。ツーリング途中の休憩に最適。', 'nickname' => 'セロー250'],
        ['rating' => 4, 'body' => '場所がわかりやすくて初めてでも迷わなかった。また利用します。', 'nickname' => 'ニンジャ乗り'],
        ['rating' => 3, 'body' => '夜は少し暗いけど、防犯カメラがあるので安心感はある。', 'nickname' => 'バイク通勤マン'],
        ['rating' => 5, 'body' => '目的地の目の前で最高。これ以上のロケーションはない。', 'nickname' => 'ドゥカティスト'],
        ['rating' => 4, 'body' => '地面がアスファルトで安定している。サイドスタンドも安心。', 'nickname' => 'GSX乗り'],
        ['rating' => 2, 'body' => '週末は満車になることが多い。平日なら問題ない。', 'nickname' => '都内ライダー'],
        ['rating' => 5, 'body' => '管理がしっかりしていてきれい。月極も検討中。', 'nickname' => 'SR乗り'],
        ['rating' => 4, 'body' => '近くにコンビニもあって便利。ちょっとした買い物にも。', 'nickname' => 'PCX通勤'],
        ['rating' => 3, 'body' => '原付きには広いが大型だとちょっとキツいかも。', 'nickname' => 'カブ主'],
        ['rating' => 4, 'body' => 'バイク専用なので車に傷つけられる心配がなくて良い。', 'nickname' => 'Z900乗り'],
        ['rating' => 5, 'body' => '屋根付き・施錠ありで安心して停められる。おすすめ！', 'nickname' => 'MT-07乗り'],
    ];

    // 人気エリア（主要都市）の都道府県
    private const POPULAR_PREFECTURES = [
        '東京都', '神奈川県', '大阪府', '愛知県', '福岡県',
        '埼玉県', '千葉県', '京都府', '兵庫県', '北海道',
    ];

    public function handle(): void
    {
        $count = (int) $this->option('count');
        $created = 0;

        // 人気エリアの駐車場を取得（レビューがまだないもの優先）
        $parkings = BikeParking::active()
            ->whereIn('prefecture', self::POPULAR_PREFECTURES)
            ->where('reviews_count', 0)
            ->whereNotNull('latitude')
            ->inRandomOrder()
            ->limit($count)
            ->get();

        if ($parkings->isEmpty()) {
            // レビューなしがない場合は全体から
            $parkings = BikeParking::active()
                ->whereIn('prefecture', self::POPULAR_PREFECTURES)
                ->whereNotNull('latitude')
                ->inRandomOrder()
                ->limit($count)
                ->get();
        }

        $bar = $this->output->createProgressBar($parkings->count());
        $bar->start();

        foreach ($parkings as $parking) {
            $template = self::TEMPLATES[array_rand(self::TEMPLATES)];

            // 日付をランダムに過去3ヶ月以内に設定
            $daysAgo = random_int(1, 90);

            ParkingReview::create([
                'bike_parking_id' => $parking->id,
                'nickname' => $template['nickname'],
                'rating' => $template['rating'],
                'body' => $template['body'],
                'visited_at' => now()->subDays($daysAgo)->toDateString(),
                'created_at' => now()->subDays(random_int(0, $daysAgo)),
            ]);

            // avg_rating と reviews_count を再計算
            $parking->update([
                'avg_rating' => $parking->reviews()->avg('rating'),
                'reviews_count' => $parking->reviews()->count(),
            ]);

            $created++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("完了！ {$created}件のシードレビューを投稿しました。");
    }
}
