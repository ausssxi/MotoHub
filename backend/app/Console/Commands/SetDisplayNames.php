<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use Illuminate\Console\Command;

final class SetDisplayNames extends Command
{
    protected $signature = 'models:set-display-names
                            {--dry-run : 変更内容をプレビュー（DBに保存しない）}
                            {--force : display_name設定済みのものも上書き}';

    protected $description = 'bike_modelsのdisplay_nameをnameから自動生成して一括設定';

    /**
     * 既知モデル名の正式表記マッピング（小文字キー → 正式表記）
     */
    private const KNOWN_MODELS = [
        // Honda
        'pcx160' => 'PCX160',
        'pcx125' => 'PCX125',
        'pcx150' => 'PCX150',
        'pcx' => 'PCX',
        'cbr250rr' => 'CBR250RR',
        'cbr250r' => 'CBR250R',
        'cbr400r' => 'CBR400R',
        'cbr600rr' => 'CBR600RR',
        'cbr650r' => 'CBR650R',
        'cbr1000rr' => 'CBR1000RR',
        'cbr1000rr-r' => 'CBR1000RR-R',
        'cb250r' => 'CB250R',
        'cb400sf' => 'CB400SF',
        'cb400sb' => 'CB400SB',
        'cb400f' => 'CB400F',
        'cb650r' => 'CB650R',
        'cb750' => 'CB750',
        'cb1100' => 'CB1100',
        'cb1300sf' => 'CB1300SF',
        'cb1300sb' => 'CB1300SB',
        'cb125r' => 'CB125R',
        'crf250l' => 'CRF250L',
        'crf250 rally' => 'CRF250 Rally',
        'crf1000l' => 'CRF1000L',
        'crf1100l' => 'CRF1100L',
        'ct125' => 'CT125',
        'dax125' => 'DAX125',
        'gb350' => 'GB350',
        'gb350s' => 'GB350S',
        'grom' => 'GROM',
        'hawk11' => 'Hawk 11',
        'nc750x' => 'NC750X',
        'nc750s' => 'NC750S',
        'nc700x' => 'NC700X',
        'rebel250' => 'Rebel 250',
        'rebel500' => 'Rebel 500',
        'rebel1100' => 'Rebel 1100',
        'vfr800' => 'VFR800',
        'vfr800f' => 'VFR800F',
        'x-adv' => 'X-ADV',
        'xadv' => 'X-ADV',
        'adv150' => 'ADV150',
        'adv160' => 'ADV160',
        'forza' => 'FORZA',
        'forza250' => 'FORZA 250',
        'forza350' => 'FORZA 350',
        'dio110' => 'Dio 110',
        'lead125' => 'Lead 125',
        'monkey125' => 'Monkey 125',
        'super cub 110' => 'Super Cub 110',
        'super cub c125' => 'Super Cub C125',
        'super cub 50' => 'Super Cub 50',
        'nsx' => 'NSX',
        'nsr250r' => 'NSR250R',
        'vtr250' => 'VTR250',
        'vtr1000f' => 'VTR1000F',
        'xl883' => 'XL883',

        // Yamaha
        'yzf-r25' => 'YZF-R25',
        'yzf-r3' => 'YZF-R3',
        'yzf-r6' => 'YZF-R6',
        'yzf-r7' => 'YZF-R7',
        'yzf-r1' => 'YZF-R1',
        'yzf-r1m' => 'YZF-R1M',
        'yzf-r15' => 'YZF-R15',
        'yzfr25' => 'YZF-R25',
        'yzfr3' => 'YZF-R3',
        'yzfr1' => 'YZF-R1',
        'mt-25' => 'MT-25',
        'mt-03' => 'MT-03',
        'mt-07' => 'MT-07',
        'mt-09' => 'MT-09',
        'mt-10' => 'MT-10',
        'mt25' => 'MT-25',
        'mt03' => 'MT-03',
        'mt07' => 'MT-07',
        'mt09' => 'MT-09',
        'mt10' => 'MT-10',
        'xsr700' => 'XSR700',
        'xsr900' => 'XSR900',
        'xsr155' => 'XSR155',
        'xmax' => 'XMAX',
        'xmax250' => 'XMAX 250',
        'xmax300' => 'XMAX 300',
        'nmax' => 'NMAX',
        'nmax125' => 'NMAX 125',
        'nmax155' => 'NMAX 155',
        'tmax' => 'TMAX',
        'tmax530' => 'TMAX 530',
        'tmax560' => 'TMAX 560',
        'sr400' => 'SR400',
        'bolt' => 'BOLT',
        'bolt950' => 'BOLT 950',
        'wr250r' => 'WR250R',
        'wr250x' => 'WR250X',
        'tenere700' => 'Ténéré 700',
        'tracer9' => 'TRACER 9',
        'tracer900' => 'TRACER 900',
        'tracer9 gt' => 'TRACER 9 GT',
        'fz25' => 'FZ25',
        'fz1' => 'FZ1',
        'fz6' => 'FZ6',
        'fz8' => 'FZ8',
        'fjr1300' => 'FJR1300',
        'majesty' => 'Majesty',
        'majesty250' => 'Majesty 250',
        'majesty s' => 'Majesty S',
        'vmax' => 'VMAX',
        'vmax1200' => 'VMAX 1200',
        'dragstar250' => 'DragStar 250',
        'dragstar400' => 'DragStar 400',
        'dragstar1100' => 'DragStar 1100',
        'serow250' => 'SEROW 250',
        'tricker' => 'TRICKER',
        'tw200' => 'TW200',
        'tw225' => 'TW225',
        'cygnus x' => 'CYGNUS X',
        'cygnus125' => 'CYGNUS 125',
        'jog' => 'JOG',

        // Kawasaki
        'ninja250' => 'Ninja 250',
        'ninja400' => 'Ninja 400',
        'ninja650' => 'Ninja 650',
        'ninja1000' => 'Ninja 1000',
        'ninja1000sx' => 'Ninja 1000SX',
        'ninja zx-25r' => 'Ninja ZX-25R',
        'ninja zx-4r' => 'Ninja ZX-4R',
        'ninja zx-4rr' => 'Ninja ZX-4RR',
        'ninja zx-6r' => 'Ninja ZX-6R',
        'ninja zx-10r' => 'Ninja ZX-10R',
        'ninja zx-14r' => 'Ninja ZX-14R',
        'ninja h2' => 'Ninja H2',
        'ninja h2 sx' => 'Ninja H2 SX',
        'z250' => 'Z250',
        'z400' => 'Z400',
        'z650' => 'Z650',
        'z900' => 'Z900',
        'z900rs' => 'Z900RS',
        'z900rs cafe' => 'Z900RS CAFE',
        'z1000' => 'Z1000',
        'z h2' => 'Z H2',
        'zx-25r' => 'ZX-25R',
        'zx-4r' => 'ZX-4R',
        'zx-6r' => 'ZX-6R',
        'zx-10r' => 'ZX-10R',
        'zx-14r' => 'ZX-14R',
        'zx25r' => 'ZX-25R',
        'zx6r' => 'ZX-6R',
        'zx10r' => 'ZX-10R',
        'zx14r' => 'ZX-14R',
        'versys650' => 'Versys 650',
        'versys1000' => 'Versys 1000',
        'versys-x 250' => 'Versys-X 250',
        'vulcan s' => 'Vulcan S',
        'vulcan900' => 'Vulcan 900',
        'w800' => 'W800',
        'w650' => 'W650',
        'klx250' => 'KLX250',
        'klx230' => 'KLX230',
        'eliminator' => 'Eliminator',
        'eliminator250' => 'Eliminator 250',
        'eliminator400' => 'Eliminator 400',
        'd-tracker' => 'D-TRACKER',

        // Suzuki
        'gsx250r' => 'GSX250R',
        'gsx-r125' => 'GSX-R125',
        'gsx-r250' => 'GSX-R250',
        'gsx-r600' => 'GSX-R600',
        'gsx-r750' => 'GSX-R750',
        'gsx-r1000' => 'GSX-R1000',
        'gsx-r1000r' => 'GSX-R1000R',
        'gsxr125' => 'GSX-R125',
        'gsxr250' => 'GSX-R250',
        'gsxr600' => 'GSX-R600',
        'gsxr750' => 'GSX-R750',
        'gsxr1000' => 'GSX-R1000',
        'gsx-s125' => 'GSX-S125',
        'gsx-s750' => 'GSX-S750',
        'gsx-s1000' => 'GSX-S1000',
        'gsx-s1000f' => 'GSX-S1000F',
        'gsx-s1000gt' => 'GSX-S1000GT',
        'gsxs1000' => 'GSX-S1000',
        'sv650' => 'SV650',
        'sv650x' => 'SV650X',
        'sv400' => 'SV400',
        'v-strom250' => 'V-Strom 250',
        'v-strom650' => 'V-Strom 650',
        'v-strom1000' => 'V-Strom 1000',
        'v-strom1050' => 'V-Strom 1050',
        'vstrom250' => 'V-Strom 250',
        'vstrom650' => 'V-Strom 650',
        'vstrom1050' => 'V-Strom 1050',
        'burgman200' => 'Burgman 200',
        'burgman400' => 'Burgman 400',
        'address110' => 'Address 110',
        'address125' => 'Address 125',
        'gixxer' => 'GIXXER',
        'gixxer250' => 'GIXXER 250',
        'gixxer sf250' => 'GIXXER SF 250',
        'katana' => 'KATANA',
        'katana1000' => 'KATANA 1000',
        'hayabusa' => 'Hayabusa',
        'gsx1300r' => 'GSX1300R Hayabusa',
        'dr-z400' => 'DR-Z400',
        'drz400' => 'DR-Z400',
        'bandit1250' => 'Bandit 1250',
        'bandit250' => 'Bandit 250',
        'gsr250' => 'GSR250',
        'gsr400' => 'GSR400',
        'gsr750' => 'GSR750',
        'inazuma400' => 'Inazuma 400',
        'lets' => "Let's",
        'swish' => 'SWISH',

        // BMW
        'r1250gs' => 'R1250GS',
        'r1250gs adventure' => 'R1250GS Adventure',
        'r1250rt' => 'R1250RT',
        'r1250rs' => 'R1250RS',
        'r1200gs' => 'R1200GS',
        'r ninet' => 'R nineT',
        'rninet' => 'R nineT',
        'f750gs' => 'F750GS',
        'f850gs' => 'F850GS',
        'f900r' => 'F900R',
        'f900xr' => 'F900XR',
        's1000rr' => 'S1000RR',
        's1000r' => 'S1000R',
        's1000xr' => 'S1000XR',
        'c400x' => 'C400X',
        'c400gt' => 'C400GT',
        'ce 04' => 'CE 04',
        'g310r' => 'G310R',
        'g310gs' => 'G310GS',
        'k1600gt' => 'K1600GT',
        'k1600gtl' => 'K1600GTL',
        'r18' => 'R18',

        // Ducati
        'panigale v4' => 'Panigale V4',
        'panigale v2' => 'Panigale V2',
        'monster' => 'Monster',
        'monster821' => 'Monster 821',
        'monster797' => 'Monster 797',
        'monster1200' => 'Monster 1200',
        'multistrada v4' => 'Multistrada V4',
        'multistrada 950' => 'Multistrada 950',
        'scrambler' => 'Scrambler',
        'scrambler icon' => 'Scrambler Icon',
        'diavel' => 'Diavel',
        'diavel v4' => 'Diavel V4',
        'streetfighter v4' => 'Streetfighter V4',
        'xdiavel' => 'XDiavel',
        'hypermotard' => 'Hypermotard',
        'hypermotard 950' => 'Hypermotard 950',
        'desert x' => 'DesertX',
        'desertx' => 'DesertX',

        // Harley-Davidson
        'sportster s' => 'Sportster S',
        'iron 883' => 'Iron 883',
        'iron883' => 'Iron 883',
        'forty-eight' => 'Forty-Eight',
        'xl883n' => 'XL883N Iron 883',
        'xl1200x' => 'XL1200X Forty-Eight',
        'fxbb' => 'FXBB Street Bob',
        'fxbrs' => 'FXBRS Breakout',
        'flhx' => 'FLHX Street Glide',
        'flhxs' => 'FLHXS Street Glide Special',
        'fltrx' => 'FLTRX Road Glide',
        'flhr' => 'FLHR Road King',
        'fxdr' => 'FXDR 114',
        'fat boy' => 'Fat Boy',
        'fat bob' => 'Fat Bob',
        'low rider' => 'Low Rider',
        'breakout' => 'Breakout',
        'street glide' => 'Street Glide',
        'road glide' => 'Road Glide',
        'road king' => 'Road King',
        'nightster' => 'Nightster',
        'pan america' => 'Pan America',
        'livewire' => 'LiveWire',

        // KTM
        'duke125' => 'Duke 125',
        'duke200' => 'Duke 200',
        'duke250' => 'Duke 250',
        'duke390' => 'Duke 390',
        'duke690' => 'Duke 690',
        'duke790' => 'Duke 790',
        'duke890' => 'Duke 890',
        'duke1290' => 'Duke 1290',
        '390duke' => '390 Duke',
        '250duke' => '250 Duke',
        '125duke' => '125 Duke',
        '890duke' => '890 Duke',
        'rc390' => 'RC 390',
        'rc250' => 'RC 250',
        '1290 super duke r' => '1290 Super Duke R',
        '1290 super adventure' => '1290 Super Adventure',
        '390 adventure' => '390 Adventure',
        '250 adventure' => '250 Adventure',

        // Triumph
        'street triple' => 'Street Triple',
        'speed triple' => 'Speed Triple',
        'tiger800' => 'Tiger 800',
        'tiger900' => 'Tiger 900',
        'bonneville t120' => 'Bonneville T120',
        'bonneville t100' => 'Bonneville T100',
        'thruxton' => 'Thruxton',
        'scrambler 900' => 'Scrambler 900',
        'trident660' => 'Trident 660',
        'trident 660' => 'Trident 660',
        'rocket 3' => 'Rocket 3',

        // Indian
        'scout' => 'Scout',
        'scout bobber' => 'Scout Bobber',
        'chief' => 'Chief',
        'ftr1200' => 'FTR 1200',

        // Aprilia
        'rs660' => 'RS 660',
        'tuono660' => 'Tuono 660',
        'tuono v4' => 'Tuono V4',
        'rsv4' => 'RSV4',
        'sr gt200' => 'SR GT 200',
        'sx125' => 'SX 125',

        // Piaggio / Vespa
        'mp3 250rl' => 'MP3 250RL',
        'mp3 500' => 'MP3 500',
        'beverly' => 'Beverly',
        'medley' => 'Medley',

        // その他
        'i me125' => 'iMe 125',
        'legend250' => 'Legend 250',
        'legend400' => 'Legend 400',
    ];

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isForce = (bool) $this->option('force');

        $query = BikeModel::with('manufacturer')->orderBy('manufacturer_id')->orderBy('name');

        if (! $isForce) {
            $query->whereNull('display_name');
        }

        $models = $query->get();

        if ($models->isEmpty()) {
            $this->info('対象レコードがありません。');

            return self::SUCCESS;
        }

        $this->info("対象レコード: {$models->count()}件");
        $this->newLine();

        $tableRows = [];
        $updated = 0;
        $skipped = 0;

        foreach ($models as $model) {
            $displayName = $this->convertName($model->name);
            $maker = $model->manufacturer?->name ?? '';

            if ($displayName === $model->name && ! $isForce) {
                $skipped++;

                continue;
            }

            $tableRows[] = [
                $model->id,
                $maker,
                $model->name,
                $displayName,
            ];

            if (! $isDryRun) {
                $model->update(['display_name' => $displayName]);
            }

            $updated++;
        }

        if (count($tableRows) > 0) {
            $this->table(['ID', 'メーカー', 'name（変換前）', 'display_name（変換後）'], $tableRows);
        }

        $this->newLine();
        $label = $isDryRun ? 'プレビュー' : '更新';
        $this->info("{$label}: {$updated}件" . ($skipped > 0 ? "（変換不要スキップ: {$skipped}件）" : ''));

        if ($isDryRun && $updated > 0) {
            $this->newLine();
            $this->comment('実行するには --dry-run を外してください:');
            $this->comment('  php artisan models:set-display-names');
        }

        return self::SUCCESS;
    }

    private function convertName(string $name): string
    {
        // 1. 日本語（ひらがな・カタカナ・漢字）を含む → そのままコピー
        if ($this->containsJapanese($name)) {
            return $name;
        }

        // 2. 既知マッピングで完全一致（小文字比較）
        $lower = mb_strtolower(trim($name));
        if (isset(self::KNOWN_MODELS[$lower])) {
            return self::KNOWN_MODELS[$lower];
        }

        // 3. 自動整形
        return $this->autoFormat($name);
    }

    private function containsJapanese(string $text): bool
    {
        return (bool) preg_match('/[\p{Hiragana}\p{Katakana}\p{Han}]/u', $text);
    }

    private function autoFormat(string $name): string
    {
        // スペースやハイフンで区切られているパーツをそれぞれ大文字化
        $parts = (array) preg_split('/([\s\-]+)/', trim($name), -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = [];

        foreach ($parts as $part) {
            // デリミタ（スペースやハイフン）はそのまま
            if (preg_match('/^[\s\-]+$/', $part)) {
                $result[] = $part;

                continue;
            }

            $upper = mb_strtoupper($part);

            // 2文字以上の英字 + 数字（+ 末尾英字）のパターンにスペース挿入
            // 例: REBEL250 → REBEL 250, NINJA400 → NINJA 400
            // ただし CB400SF, GSX250R 等の型番パターン（短い英字プレフィックス）は維持
            $upper = (string) preg_replace('/^([A-Z]{4,})(\d+)([A-Z]*)$/', '$1 $2$3', $upper);

            $result[] = $upper;
        }

        return implode('', $result);
    }
}
