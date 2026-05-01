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
        // Stores all chats for AI interrogations across one or more documents.
        Schema::connection('mongodb')->create('ai_interrogation_chats', function (Blueprint $collection) {
            // Keep these indexes consistent with the document interrogation chats collection.
            $collection->index('user_id');
            $collection->index('created_at');
            $collection->index('updated_at');

            $collection->index(['document_id' => 1, 'created_at' => -1]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->drop('ai_interrogation_chats');
    }
};
