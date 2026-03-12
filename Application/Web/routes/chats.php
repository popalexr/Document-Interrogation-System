<?php

use App\Http\Controllers\Chats\DeleteAllChatsForDocumentController;
use App\Http\Controllers\Chats\DeleteChatController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('chats')->group(function () {
    Route::post('/delete', DeleteChatController::class)
        ->name('chats.delete');
    Route::post('/deleteAll', DeleteAllChatsForDocumentController::class)
        ->name('chats.deleteAll');
});