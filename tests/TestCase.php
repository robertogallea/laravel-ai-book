<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    /**
     * The array cache store is a single process-wide instance, not reset
     * between tests the way RefreshDatabase resets the database: without
     * this, a cache key one test happens to write (see
     * AskSpendingCommand's spending-answer cache) could still be there,
     * unexpectedly, when a later, unrelated test computes the same key.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }
}
