<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$stations = App\Models\Station::select('name','prefecture','is_major','slug')
    ->orderBy('prefecture')
    ->orderBy('name')
    ->get();

$unique = [];
foreach ($stations as $s) {
    if (!isset($unique[$s->name])) {
        $unique[$s->name] = $s->prefecture . '|' . ($s->is_major ? 'M' : '-') . '|' . $s->slug;
    }
}

echo 'UNIQUE:' . count($unique) . "\n";
foreach ($unique as $name => $info) {
    echo $name . '|' . $info . "\n";
}
