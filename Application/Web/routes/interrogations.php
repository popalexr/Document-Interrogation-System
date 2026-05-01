<?php

use App\Http\Controllers\Interrogations\InterrogationsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/interrogations', InterrogationsController::class)
        ->name('interrogations.index');

    Route::post('/interrogations', [InterrogationsController::class, 'store'])
        ->name('interrogations.store');

    Route::post('/interrogations/delete', [InterrogationsController::class, 'delete'])
        ->name('interrogations.delete');

    Route::post('/interrogations/deleteAll', [InterrogationsController::class, 'deleteAll'])
        ->name('interrogations.deleteAll');
});
