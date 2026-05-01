<?php

use App\Http\Controllers\Welcome\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

require __DIR__.'/settings.php';
require __DIR__.'/dashboard.php';
require __DIR__.'/documents.php';
require __DIR__.'/interrogations.php';
require __DIR__.'/chats.php';
require __DIR__.'/files.php';
require __DIR__.'/trash.php';
require __DIR__.'/api.php';
require __DIR__.'/collabora.php';
