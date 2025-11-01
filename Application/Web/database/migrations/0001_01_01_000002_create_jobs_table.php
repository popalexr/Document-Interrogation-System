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
        // jobs (database queue driver)
        Schema::connection('mongodb')->create('jobs', function (Blueprint $collection) {
            $collection->index('queue');
            $collection->index('attempts');
            $collection->index('reserved_at');
            $collection->index('available_at');
            $collection->index('created_at');
        });

        // job_batches (for Bus::batch)
        Schema::connection('mongodb')->create('job_batches', function (Blueprint $collection) {
            $collection->unique('id');
            $collection->index('name');
            $collection->index('pending_jobs');
            $collection->index('failed_jobs');
            $collection->index('cancelled_at');
            $collection->index('created_at');
            $collection->index('finished_at');
        });

        // failed_jobs
        Schema::connection('mongodb')->create('failed_jobs', function (Blueprint $collection) {
            $collection->unique('uuid');
            $collection->index('connection');
            $collection->index('queue');
            $collection->index('failed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->drop('jobs');
        Schema::connection('mongodb')->drop('job_batches');
        Schema::connection('mongodb')->drop('failed_jobs');
    }
};
