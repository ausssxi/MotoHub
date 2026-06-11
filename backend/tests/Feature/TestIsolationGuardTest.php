<?php
namespace Tests\Feature;
use Tests\TestCase;
class TestIsolationGuardTest extends TestCase
{
    /** テストが dev/本番 MySQL に触れないことの常設ガード。greenの間は安全。 */
    public function test_testing_env_is_isolated(): void
    {
        $this->assertSame('sqlite', config('database.default'),
            'テストが sqlite 以外に接続しようとしている＝dev MySQL が危険！');
        $this->assertSame(':memory:', config('database.connections.sqlite.database'),
            'sqlite が :memory: でない＝隔離が緩んでいる！');
    }
}
