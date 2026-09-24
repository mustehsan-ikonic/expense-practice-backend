<?php

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Base class for tests that need a REAL MySQL server (true concurrency, row
 * locking, a persistent database/failed_jobs queue). SQLite `:memory:` cannot
 * model any of these: each connection gets its own private database and there is
 * no SELECT ... FOR UPDATE.
 *
 * These tests are tagged `@group mysql`. If the MySQL server configured by the
 * `mysql_test` connection is unreachable, the test is skipped rather than failed,
 * so `php artisan test` still works on machines without MySQL.
 *
 * We deliberately do NOT use RefreshDatabase here: its per-test transaction would
 * hide uncommitted rows from a second connection and defeat lock testing. Instead
 * we migrate the schema fresh for each test.
 */
abstract class MySqlTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessMySqlIsAvailable();

        config(['database.default' => 'mysql_test']);

        Artisan::call('migrate:fresh', [
            '--database' => 'mysql_test',
            '--force' => true,
        ]);
    }

    protected function skipUnlessMySqlIsAvailable(): void
    {
        try {
            $this->ensureTestDatabaseExists();
            DB::connection('mysql_test')->getPdo();
        } catch (Throwable $e) {
            $this->markTestSkipped('MySQL test database is not available: '.$e->getMessage());
        }
    }

    /**
     * Create the test database if it does not exist yet, connecting to the server
     * without selecting a database first.
     */
    private function ensureTestDatabaseExists(): void
    {
        $database = config('database.connections.mysql_test.database');

        config([
            'database.connections.mysql_test_bootstrap' => array_merge(
                config('database.connections.mysql_test'),
                ['database' => null],
            ),
        ]);

        DB::connection('mysql_test_bootstrap')
            ->statement("CREATE DATABASE IF NOT EXISTS `{$database}`");

        DB::purge('mysql_test_bootstrap');
    }
}
