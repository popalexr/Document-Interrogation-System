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
        // users
        Schema::connection('mongodb')->create('users', function (Blueprint $collection) {
            $collection->unique('email');
            $collection->index('remember_token');
            $collection->index('email_verified_at');
            $collection->index('created_at');
            $collection->index('updated_at');
        });

        // password_reset_tokens
        Schema::connection('mongodb')->create('password_reset_tokens', function (Blueprint $collection) {
            $collection->unique('email');
            $collection->index('token');
            $collection->index('created_at');
        });

        // sessions (for SESSION_DRIVER=database)
        Schema::connection('mongodb')->create('sessions', function (Blueprint $collection) {
            $collection->index('user_id');
            $collection->index('ip_address');
            $collection->index('last_activity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->drop('users');
        Schema::connection('mongodb')->drop('password_reset_tokens');
        Schema::connection('mongodb')->drop('sessions');
    }
};
