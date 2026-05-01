<?php

use App\Http\Controllers\Interrogations\InterrogationsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/interrogations', InterrogationsController::class)
        ->name('interrogations.index');
});
