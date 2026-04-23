<?php

declare(strict_types=1);

namespace App\Services\Station;

final class JapaneseToRomajiConverter
{
    /** @var array<string, string> */
    private array $dictionary;

    public function __construct()
    {
        $this->dictionary = $this->loadDictionary();
    }

    /**
     * 駅名からローマ字slugを取得
     */
    public function convert(string $name): ?string
    {
        return $this->dictionary[$name] ?? null;
    }

    /**
     * 辞書にエントリーがあるか確認
     */
    public function hasEntry(string $name): bool
    {
        return isset($this->dictionary[$name]);
    }

    /**
     * @return array<string, string>
     */
    private function loadDictionary(): array
    {
        // ホスト実行時: backend/../docs/
        $dictionaryPath = base_path('../docs/station-romaji/index.php');
        if (! file_exists($dictionaryPath)) {
            // Docker環境: configにコピーされた辞書を参照
            $dictionaryPath = config_path('station-romaji/index.php');
        }

        if (! file_exists($dictionaryPath)) {
            return [];
        }

        $data = require $dictionaryPath;

        return is_array($data) ? $data : [];
    }
}
