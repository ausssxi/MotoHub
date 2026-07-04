<?php

it('defines the r2 (S3-compatible) disk', function () {
    $r2 = config('filesystems.disks.r2');
    expect($r2)->not->toBeNull()
        ->and($r2['driver'])->toBe('s3')
        ->and($r2)->toHaveKeys(['key', 'secret', 'bucket', 'endpoint', 'use_path_style_endpoint']);
});

it('falls back to the local disk when no R2 endpoint is configured', function () {
    // テスト環境には R2_ENDPOINT が無いので保存先は local
    expect(config('backup.backup.destination.disks'))->toBe(['local'])
        ->and(config('backup.monitor_backups.0.disks'))->toBe(['local']);
});

it('excludes only transient tables from the db dump and keeps trouble_events', function () {
    $exclude = config('database.connections.mysql.dump.exclude_tables');
    expect($exclude)->toBe(['sessions', 'cache', 'cache_locks', 'jobs'])
        ->and($exclude)->not->toContain('trouble_events')
        ->and(config('database.connections.mysql.dump.use_single_transaction'))->toBeTrue();
});

it('encrypts the archive and caps storage inside the R2 free tier', function () {
    expect(config('backup.backup.encryption'))->toBe('default')
        ->and(config('backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than'))->toBe(9000)
        ->and(config('backup.monitor_backups.0.health_checks'))->not->toBeEmpty();
});

it('notifies on failure but not on success', function () {
    $n = config('backup.notifications.notifications');
    expect($n[\Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class])->toBe(['mail'])
        ->and($n[\Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class])->toBe([]);
});
