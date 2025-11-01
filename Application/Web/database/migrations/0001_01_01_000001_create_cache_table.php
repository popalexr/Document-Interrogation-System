<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // cache
        Schema::connection('mongodb')->create('cache', function (Blueprint $collection) {
            $collection->index('expiration');
        });

        // cache_locks
        Schema::connection('mongodb')->create('cache_locks', function (Blueprint $collection) {
            $collection->index('owner');
            $collection->index('expiration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->drop('cache');
        Schema::connection('mongodb')->drop('cache_locks');
    }
};
