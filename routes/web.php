<?php

use App\Http\Controllers\AttachmentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

Route::post('/chat/upload', [AttachmentController::class, 'upload']);
Route::post('/chat', [ChatController::class, 'chat']);
Route::post('/chat/stream', [ChatController::class, 'chatStream']);
Route::post('/session/init', [ChatController::class, 'initSession']);
Route::get('/session', [ChatController::class, 'getSession']);
Route::get('/sessions', [ChatController::class, 'getSessions']);
Route::post('/session/switch', [ChatController::class, 'switchSession']);
Route::post('/session/delete', [ChatController::class, 'deleteSession']);
Route::view('/', 'chat');