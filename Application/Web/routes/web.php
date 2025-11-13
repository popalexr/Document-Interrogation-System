<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\Upload\UploadController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Documents\InterrogateDocumentController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    // File upload to Cloudflare R2 (quarantine)
    Route::post('/uploads', [UploadController::class, 'store'])->name('uploads.store');

    // Document interrogation
    Route::get('/documents/interrogate', [InterrogateDocumentController::class, 'index'])
        ->name('documents.interrogate');
    Route::post('/documents/interrogate', [InterrogateDocumentController::class, 'store'])
        ->name('documents.interrogate.store');
});

require __DIR__.'/settings.php';
