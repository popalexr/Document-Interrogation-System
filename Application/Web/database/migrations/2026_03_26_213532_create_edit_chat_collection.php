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
        // Stores chats associated with a specific edited document.
        Schema::connection('mongodb')->create('edit_chats', function (Blueprint $collection) {
            $collection->index('document_id');
            $collection->index('edit_id');
            $collection->index('user_id');
            $collection->index('created_at');
            $collection->index('updated_at');

            // Supports retrieving chats for a specific edit by latest activity.
            $collection->index(['edit_id' => 1, 'created_at' => -1]);

            // Supports retrieving chats for a document by latest activity.
            $collection->index(['document_id' => 1, 'created_at' => -1]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->drop('edit_chats');
    }
};
