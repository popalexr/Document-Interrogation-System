<?php

use App\Http\Controllers\Trash\TrashController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('trash')->group(function () {
    Route::get('/', TrashController::class)->name('trash.index');
});