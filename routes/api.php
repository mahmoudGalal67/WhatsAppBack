<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\UsersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;


Broadcast::routes([
    'middleware' => ['auth:sanctum'], // 🔥 THIS is the fix
]);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/users/edit', [UsersController::class, 'editProfile']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/messages', [MessageController::class, 'send']);
    Route::post('/messages/share', [MessageController::class, 'share']);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/chats', [ChatController::class, 'index']);
    Route::post('/chats/open', [ChatController::class, 'openChat']);
    Route::get('/chats/{chat}/messages', [ChatController::class, 'messages']);
    Route::post('/chats/{chat}/read', [ChatController::class, 'markAsRead']);

    Route::post('/chats/group', [ChatController::class, 'createGroup']);
    Route::post('/messages/mark-delivered', [MessageController::class, 'markDelivered']);
    Route::post('/messages/mark-seen/{chatId}', [MessageController::class, 'markAsSeen']);
    Route::get('/users', [UsersController::class, 'index']);
    Route::post('/chats/private', [ChatController::class, 'createPrivateChat']);
    Route::post('/chats/delete', [ChatController::class, 'deleteMultipleChats']);
    Route::post('/messages/delete-multiple', [MessageController::class, 'deleteMultiple']);
    Route::post('/messages/forward', [MessageController::class, 'forward']);

});

