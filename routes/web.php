<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/chat');

Route::get('/chat', [ChatController::class, 'show'])->name('chat.show');
Route::post('/chat', [ChatController::class, 'ask'])->name('chat.ask');
Route::post('/chat/stream', [ChatController::class, 'stream'])->name('chat.stream');

Route::resource('documents', DocumentController::class)->only(['index', 'store', 'destroy']);
