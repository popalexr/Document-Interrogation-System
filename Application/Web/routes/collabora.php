<?php

use App\Http\Controllers\Collabora\CheckFileInfoController;
use App\Http\Controllers\Collabora\FileContentsController;
use App\Http\Controllers\Collabora\PreviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('collabora')->group(function () {
    Route::get('/preview', PreviewController::class)
        ->name('collabora.preview');
});

Route::prefix('collabora/wopi')->group(function () {
    Route::get('/files/{file}', CheckFileInfoController::class)
        ->name('collabora.wopi.files.show');
    Route::get('/files/{file}/contents', FileContentsController::class)
        ->name('collabora.wopi.files.contents');
});
