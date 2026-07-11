<?php

use App\Models\BikeModel;
use App\Models\BikeNews;
use App\Models\BikeParking;
use App\Models\Manufacturer;
use App\Models\ModelAnswer;
use App\Models\ModelQuestion;
use App\Models\NewsComment;
use App\Models\ParkingReview;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ugcModel(): BikeModel
{
    $mfr = new Manufacturer(['slug' => 'honda']);
    $mfr->name = 'ホンダ';
    $mfr->save();

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'レブル250', 'slug' => 'rebel-250']);
}

function ugcReportFor(object $target, string $reason = 'spam'): Report
{
    $r = new Report;
    $r->reportable_type = $target::class;
    $r->reportable_id = $target->id;
    $r->reason = $reason;
    $r->status = Report::STATUS_OPEN;
    $r->save();

    return $r;
}

// ─────────── 管理者認可（パネル全体のガード） ───────────

it('gates the admin panel to is_admin users only', function () {
    expect((new User(['is_admin' => true]))->canAccessPanel(app(\Filament\Panel::class)))->toBeTrue()
        ->and((new User(['is_admin' => false]))->canAccessPanel(app(\Filament\Panel::class)))->toBeFalse();
});

it('forbids non-admins and guests from the admin panel, allows admins', function () {
    $this->get('/admin')->assertRedirect();                       // 未認証 → ログインへ

    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->get('/admin')->assertForbidden();                       // 一般ユーザー → 403

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get('/admin')->assertSuccessful();                      // 管理者 → OK
});

// ─────────── 削除：親子カスケード＋通報purge ───────────

it('deletes a question with its answers and purges reports for both', function () {
    $m = ugcModel();
    $q = ModelQuestion::create(['bike_model_id' => $m->id, 'nickname' => 'x', 'title' => '質問', 'is_approved' => true]);
    $a1 = ModelAnswer::create(['model_question_id' => $q->id, 'nickname' => 'x', 'body' => '回答1', 'is_approved' => true]);
    $a2 = ModelAnswer::create(['model_question_id' => $q->id, 'nickname' => 'x', 'body' => '回答2', 'is_approved' => true]);

    ugcReportFor($q);
    ugcReportFor($a1);
    ugcReportFor($a2);
    expect(Report::count())->toBe(3);

    $q->delete(); // Filament DeleteAction と同じ $record->delete()

    expect(ModelQuestion::find($q->id))->toBeNull()
        ->and(ModelAnswer::whereIn('id', [$a1->id, $a2->id])->count())->toBe(0)  // DBカスケードで回答も消える
        ->and(Report::count())->toBe(0);                                        // 質問＋回答分の通報も消える
});

it('purges the report when a single answer is deleted', function () {
    $m = ugcModel();
    $q = ModelQuestion::create(['bike_model_id' => $m->id, 'nickname' => 'x', 'title' => 'q', 'is_approved' => true]);
    $a = ModelAnswer::create(['model_question_id' => $q->id, 'nickname' => 'x', 'body' => 'a', 'is_approved' => true]);
    ugcReportFor($a);

    $a->delete();

    expect(ModelAnswer::find($a->id))->toBeNull()
        ->and(Report::count())->toBe(0)
        ->and(ModelQuestion::find($q->id))->not->toBeNull(); // 親質問は残る
});

it('purges the report when a parking review is deleted', function () {
    $p = BikeParking::create(['name' => 'テスト駐輪場', 'address' => '東京都', 'latitude' => 35.0, 'longitude' => 139.0]);
    $pr = ParkingReview::create(['bike_parking_id' => $p->id, 'nickname' => 'x', 'rating' => 4, 'body' => 'よい', 'is_approved' => true]);
    ugcReportFor($pr);

    $pr->delete();

    expect(ParkingReview::find($pr->id))->toBeNull()->and(Report::count())->toBe(0);
});

it('purges the report when a news comment is deleted', function () {
    $news = BikeNews::create(['title' => 'ニュース', 'url' => 'https://example.com/n1']);
    $c = NewsComment::create(['news_id' => $news->id, 'nickname' => 'x', 'body' => 'コメント', 'is_approved' => true]);
    ugcReportFor($c);

    $c->delete();

    expect(NewsComment::find($c->id))->toBeNull()->and(Report::count())->toBe(0);
});

it('deletes cleanly when there is no report to purge', function () {
    $m = ugcModel();
    $q = ModelQuestion::create(['bike_model_id' => $m->id, 'nickname' => 'x', 'title' => 'q', 'is_approved' => true]);

    $q->delete();

    expect(ModelQuestion::find($q->id))->toBeNull()->and(Report::count())->toBe(0);
});

it('only purges reports for the deleted record, leaving others intact', function () {
    $m = ugcModel();
    $q1 = ModelQuestion::create(['bike_model_id' => $m->id, 'nickname' => 'x', 'title' => 'q1', 'is_approved' => true]);
    $q2 = ModelQuestion::create(['bike_model_id' => $m->id, 'nickname' => 'x', 'title' => 'q2', 'is_approved' => true]);
    ugcReportFor($q1);
    $keep = ugcReportFor($q2);

    $q1->delete();

    expect(Report::count())->toBe(1)
        ->and(Report::first()->id)->toBe($keep->id); // 別レコードの通報は残る
});

// ─────────── Filament リソース登録（削除UIの土台） ───────────

it('registers UGC resources under the UGC管理 group targeting the right models', function () {
    expect(\App\Filament\Resources\ModelQuestionResource::getModel())->toBe(ModelQuestion::class)
        ->and(\App\Filament\Resources\ModelAnswerResource::getModel())->toBe(ModelAnswer::class)
        ->and(\App\Filament\Resources\ParkingReviewResource::getModel())->toBe(ParkingReview::class)
        ->and(\App\Filament\Resources\NewsCommentResource::getModel())->toBe(NewsComment::class)
        ->and(\App\Filament\Resources\ModelQuestionResource::getNavigationGroup())->toBe('UGC管理');
});
