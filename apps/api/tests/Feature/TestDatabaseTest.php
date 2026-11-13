<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestDatabaseTest extends TestCase
{
    /**
     * RefreshDatabase wipes whatever database the tests connect to, so they must never
     * run against the app database, even when DB_DATABASE is set in the process env (as in CI).
     */
    public function test_tests_use_the_dedicated_test_database(): void
    {
        $this->assertSame('request_desk_test', DB::connection()->getDatabaseName());
    }
}
