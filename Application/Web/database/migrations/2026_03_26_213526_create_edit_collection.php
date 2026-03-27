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
        // Stores editable document snapshots derived from original uploads.
        Schema::connection('mongodb')->create('edits', function (Blueprint $collection) {
            $collection->index('original_document_id');
            $collection->index('user_id');
            $collection->index('r2_key');
            $collection->index('created_at');
            $collection->index('updated_at');

            // Supports listing edits for a document in reverse chronological order.
            $collection->index(['original_document_id' => 1, 'created_at' => -1]);

            // Supports listing a user's edits in reverse chronological order.
            $collection->index(['user_id' => 1, 'created_at' => -1]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->drop('edits');
    }
};
