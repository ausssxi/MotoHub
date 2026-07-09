<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;

/**
 * ユーザーからの不適切投稿の通報を受け付ける。
 * - 生IPは保存せず sha256(ip|app.key) のみ（既存の投稿系と同一）。
 * - 同一IPハッシュ × 同一対象の重複通報は新規作成しない（濫用・二重通報の soft-handling）。
 * - 通報したことは他ユーザーに見えない（晒し防止）。
 */
final class ReportController extends Controller
{
    public function store(StoreReportRequest $request): RedirectResponse
    {
        $class = Report::REPORTABLE_TYPES[$request->string('type')->value()];
        $id = (int) $request->integer('id');

        // 対象が実在する承認済み投稿のときだけ受け付ける（存在しないID通報を弾く）。
        $target = $class::find($id);
        if ($target === null) {
            return back()->with('report_success', '1'); // 存在有無は明かさず一律「受付」表示
        }

        $ipHash = hash('sha256', $request->ip().'|'.config('app.key'));

        // 二重通報の soft-handling: 既存があればそれを使い、新規作成しない。
        Report::firstOrCreate(
            [
                'reportable_type' => $class,
                'reportable_id' => $id,
                'reporter_ip_hash' => $ipHash,
            ],
            [
                'reason' => $request->input('reason'),
                'status' => Report::STATUS_OPEN,
            ],
        );

        return back()->with('report_success', '1');
    }
}
