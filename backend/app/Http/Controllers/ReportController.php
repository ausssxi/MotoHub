<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Models\Report;
use App\Models\User;
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
        $type = $request->string('type')->value();
        $class = Report::REPORTABLE_TYPES[$type];

        // アバター通報は public_token で対象ユーザーを解決（連番 user id は DOM に一切出さない）。
        // それ以外は従来どおり数値 id。対象が実在するときだけ受け付ける（存在しない対象は弾く）。
        if ($type === 'user_avatar') {
            $target = User::where('public_token', $request->string('token')->value())->first();
        } else {
            $target = $class::find((int) $request->integer('id'));
        }

        if ($target === null) {
            return back()->with('report_success', '1'); // 存在有無は明かさず一律「受付」表示
        }

        $id = (int) $target->getKey();
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
