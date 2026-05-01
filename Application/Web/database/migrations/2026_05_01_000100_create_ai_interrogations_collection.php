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
        // Stores AI interrogation messages for chats across one or more documents.
        Schema::connection('mongodb')->create('ai_interrogations', function (Blueprint $collection) {
            // Keep these indexes consistent with the document interrogation messages collection.
            $collection->index('chat_id');
            $collection->index('documents_ids');
            $collection->index('created_at');
            $collection->index('updated_at');

            $collection->index(['documents_ids' => 1, 'created_at' => -1]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->drop('ai_interrogations');
    }
};
