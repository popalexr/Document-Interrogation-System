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
        // Stores edit assistant messages for a specific edit chat.
        Schema::connection('mongodb')->create('edit_interrogations', function (Blueprint $collection) {
            $collection->index('chat_id');
            $collection->index('created_at');
            $collection->index('updated_at');

            // Supports replaying a chat in message order.
            $collection->index(['chat_id' => 1, 'created_at' => 1]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->drop('edit_interrogations');
    }
};
