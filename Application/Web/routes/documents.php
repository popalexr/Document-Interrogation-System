<?php

use App\Http\Controllers\Documents\InterrogateDocumentController;
use App\Http\Controllers\Upload\UploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // File upload to Cloudflare R2 (quarantine)
    Route::post('/uploads', [UploadController::class, 'store'])->name('uploads.store');
});

Route::middleware(['auth'])->prefix('documents')->group(function () {
    Route::get('/interrogate', [InterrogateDocumentController::class, 'index'])
        ->name('documents.interrogate');
    Route::post('/interrogate', [InterrogateDocumentController::class, 'store'])
        ->name('documents.interrogate.store');
});