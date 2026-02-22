<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use SerializesModels;

    public $message;
    public $receiverId;

    public function __construct(Message $message, $receiverId)
    {
        $this->message = $message->load('user', 'replyMessage');
        $this->receiverId = $receiverId;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('chat.' . $this->message->chat_id),
            new PrivateChannel('user.' . $this->receiverId),
        ];
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->message->id,
            'chat_id' => $this->message->chat_id,
            'user' => $this->message->user,
            'body' => $this->message->body,
            'type' => $this->message->type,
            'file_path' => $this->message->file_path,
            'reply_to' => $this->message->replyMessage,
            'created_at' => $this->message->created_at->toDateTimeString(),
        ];
    }
}
