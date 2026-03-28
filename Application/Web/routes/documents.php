<?php

use App\Http\Controllers\Documents\DeleteDocumentController;
use App\Http\Controllers\Documents\EditDocumentController;
use App\Http\Controllers\Documents\InterrogateDocumentController;
use App\Http\Controllers\Documents\ViewDocumentController;
use App\Http\Controllers\Files\DownloadFileController;
use App\Http\Controllers\Upload\UploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // File upload to Cloudflare R2 (quarantine)
    Route::post('/uploads', [UploadController::class, 'store'])->name('uploads.store');
    Route::get('/downloadFile', DownloadFileController::class)
        ->name('documents.downloadDocument');
});

Route::middleware(['auth'])->prefix('documents')->group(function () {
    Route::get('/view', ViewDocumentController::class)
        ->name('documents.view');
    Route::get('/interrogate', [InterrogateDocumentController::class, 'index'])
        ->name('documents.interrogate');
    Route::get('/edit', EditDocumentController::class)
        ->name('documents.edit');

    Route::post('/interrogate', [InterrogateDocumentController::class, 'store'])
        ->name('documents.interrogate.store');
    Route::post('/edit', [EditDocumentController::class, 'store'])
        ->name('documents.edit_document');
    Route::post('/delete', DeleteDocumentController::class)
        ->name('documents.delete');
});
