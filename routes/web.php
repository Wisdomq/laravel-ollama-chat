<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

// Chat pipeline
Route::post('/chat/upload', [AttachmentController::class, 'upload']);
Route::post('/chat', [ChatController::class, 'chat']);
Route::post('/chat/stream', [ChatController::class, 'chatStream']);
Route::post('/session/init', [ChatController::class, 'initSession']);
Route::get('/session', [ChatController::class, 'getSession']);
Route::get('/sessions', [ChatController::class, 'getSessions']);
Route::post('/session/switch', [ChatController::class, 'switchSession']);
Route::post('/session/delete', [ChatController::class, 'deleteSession']);
Route::view('/chat', 'chat')->name('chat');

// Landing
Route::get('/', [WorkflowController::class, 'landing'])->name('landing');

// Workflow pipeline
Route::prefix('workflow')->name('workflow.')->group(function () {
    Route::get('/', [WorkflowController::class, 'create'])->name('create');
    Route::post('/upload', [WorkflowController::class, 'upload'])->name('upload');
    Route::post('/refine', [WorkflowController::class, 'refine'])->name('refine');
    Route::post('/approve', [WorkflowController::class, 'approve'])->name('approve');
    Route::post('/reset', [WorkflowController::class, 'reset'])->name('reset');
    Route::get('/generations', [WorkflowController::class, 'generations'])->name('generations');
    Route::get('/status/{jobId}', [WorkflowController::class, 'status'])->name('status');
    Route::get('/result/{jobId}', [WorkflowController::class, 'result'])->name('result');
});
