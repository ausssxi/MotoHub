<?php
// コンテナの実env DB_DATABASE=motohub が $_SERVER に居座り、
// phpunit の force を上書きしてしまう。テスト中は強制で潰す。
$_SERVER['DB_CONNECTION'] = $_ENV['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE']   = $_ENV['DB_DATABASE']   = ':memory:';
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
require __DIR__.'/../vendor/autoload.php';
