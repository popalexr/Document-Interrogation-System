<?php

use App\Http\Controllers\API\GenerateTitleAPIController;
use App\Http\Controllers\API\ViewFileAPIController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function() {
    Route::get('/viewFile', ViewFileAPIController::class)
        ->name('api.viewFile');
    Route::post('/generate_title', GenerateTitleAPIController::class)
        ->name('api.generateTitle');
});