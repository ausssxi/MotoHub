<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class TrendCard extends Component
{
    /**
     * コンポーネントに渡されるデータ
     */
    public function __construct(
        public array $item,
        public int $index,
        public string $type
    ) {}

    /**
     * 以下、Bladeから呼び出せる判定メソッド群
     */
    public function isDrop(): bool
    {
        return $this->type === 'drop';
    }

    public function colorClass(): string
    {
        return $this->isDrop() ? 'text-blue-600' : 'text-red-600';
    }

    public function bgClass(): string
    {
        return $this->isDrop() ? 'bg-blue-50' : 'bg-red-50';
    }

    public function icon(): string
    {
        return $this->isDrop() ? 'trending-down' : 'trending-up';
    }

    public function sign(): string
    {
        return $this->isDrop() ? '' : '+';
    }

    public function rankColor(): string
    {
        return match ($this->index) {
            0 => 'bg-yellow-400 text-white shadow-lg shadow-yellow-400/30',
            1 => 'bg-gray-300 text-white shadow-lg shadow-gray-300/30',
            2 => 'bg-amber-600 text-white shadow-lg shadow-amber-600/30',
            default => 'bg-gray-200 text-gray-500',
        };
    }

    /**
     * 描画するビューを指定
     */
    public function render(): View
    {
        return view('components.trend-card');
    }
}