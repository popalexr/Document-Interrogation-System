<?php

use App\Http\Controllers\Files\OverrideEditedFileController;
use App\Http\Controllers\Files\SaveEditedFileAsNewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('files')->group(function () {
    Route::post('/save-as-new', SaveEditedFileAsNewController::class)
        ->name('files.save_as_new');
    Route::post('/override-edited', OverrideEditedFileController::class)
        ->name('files.override_edited');
});
