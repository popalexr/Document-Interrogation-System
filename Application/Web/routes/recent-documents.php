<?php

use App\Http\Controllers\Miscellaneous\RecentDocumentsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/recent-files', RecentDocumentsController::class)
        ->name('recent-documents.index');
});
