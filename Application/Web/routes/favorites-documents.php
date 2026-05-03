<?php

use App\Http\Controllers\Miscellaneous\FavoriteDocumentsController;
use App\Http\Controllers\Miscellaneous\MarkAsFavoriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/favorites', FavoriteDocumentsController::class)
        ->name('favorites-documents.index');

    Route::post('/favorites/mark', MarkAsFavoriteController::class)
        ->name('favorites-documents.mark');
});
