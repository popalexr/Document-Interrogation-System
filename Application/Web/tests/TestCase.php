<?php

namespace Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\MakesMongoModels;

abstract class TestCase extends BaseTestCase
{
    use MakesMongoModels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearMongoCollections();
    }

    private function clearMongoCollections(): void
    {
        if (! $this->app->environment('testing')) {
            return;
        }

        try {
            $database = DB::connection('mongodb')->getMongoDB();

            foreach ($database->listCollections() as $collection) {
                $name = $collection->getName();

                if ($name === 'migrations') {
                    continue;
                }

                $database->selectCollection($name)->deleteMany([]);
            }
        } catch (\Throwable) {
            // Tests that fully mock MongoDB should not require a running server.
        }
    }
}
