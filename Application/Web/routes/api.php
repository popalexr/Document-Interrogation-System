<?php

use App\Http\Controllers\API\GenerateTitleAPIController;
use App\Http\Controllers\API\ViewFileAPIController;
use App\Http\Controllers\API\ViewEditedFileAPIController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('api')->group(function() {
    Route::get('/viewFile', ViewFileAPIController::class)
        ->name('api.viewFile');
    Route::get('/viewEditedFile', ViewEditedFileAPIController::class)
        ->name('api.viewEditedFile');
    Route::post('/generate_title', GenerateTitleAPIController::class)
        ->name('api.generateTitle');
});
