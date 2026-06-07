<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trouble;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class TroubleController extends Controller
{
    /**
     * 症状自己診断ツール（トラブル診断）トップ。
     *
     * 決定木はクライアント側で辿る（AIなし・サーバー往復なし）。
     * データは config/diagnosis.php を view に渡すだけ。
     */
    public function index(): View
    {
        return view('trouble.index', [
            'symptoms' => config('diagnosis.symptoms', []),
            'nodes'    => config('diagnosis.nodes', []),
            'cards'    => config('diagnosis.cards', []),
            'verdicts' => config('diagnosis.verdicts', []),
        ]);
    }
}
