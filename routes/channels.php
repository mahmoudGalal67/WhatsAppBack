<?php

use App\Models\Message;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    return $user->chats()->where('chat_id', $chatId)->exists();
});

Broadcast::channel('online', function ($user) {
    return ['id' => $user->id];

});

Broadcast::channel('presence.chat.{chatId}', function ($user, $chatId) {
    return ['id' => $user->id];
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
