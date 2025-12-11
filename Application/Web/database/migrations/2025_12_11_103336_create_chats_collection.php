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
        // Stores all chats for a specific document (upload)
        Schema::connection('mongodb')->create('chats', function (Blueprint $collection) {
            // Indexes for common query patterns
            $collection->index('upload_id');     // optional: legacy/aggregate style
            $collection->index('document_id');   // per-message style (string/ObjectId of Upload)
            $collection->index('user_id');       // owner / initiator of the chat
            $collection->index('created_at');
            $collection->index('updated_at');

            // Optional compound index for efficient per-document timeline queries
            $collection->index(['document_id' => 1, 'created_at' => -1]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->drop('chats');
    }
};
