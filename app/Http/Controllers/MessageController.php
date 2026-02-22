<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'chat_id' => 'required|exists:chats,id',
            'type' => 'required',
            'body' => 'nullable|string',
            'file' => 'nullable|file|max:51200',
            'is_delivered' => 'nullable|boolean',
            'is_seen' => 'nullable|boolean',
        ]);

        $path = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $path = $file->store('chat_files', 'public');

            // Detect type automatically
            $mime = $file->getMimeType();

            if (str_contains($mime, 'image')) {
                $type = 'image';
            } elseif (str_contains($mime, 'video')) {
                $type = 'video';
            } elseif ($mime === 'application/pdf') {
                $type = 'pdf';
            } elseif (
                in_array($mime, [
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ])
            ) {
                $type = 'excel';
            } elseif (
                in_array($mime, [
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ])
            ) {
                $type = 'word';
            } else {
                $type = 'file';
            }
        } else {
            $type = $request->type;
        }

        $message = Message::create([
            'chat_id' => $request->chat_id,
            'user_id' => auth()->id(),
            'type' => $type,
            'body' => $request->body,
            'file_path' => $path,
            'reply_to' => $request->reply_to,
            'forwarded_from' => $request->forwarded_from,
            'is_delivered' => $request->is_delivered,
            'is_seen' => $request->is_seen,
        ]);

        $chat = Chat::findOrFail($request->chat_id);
        $receiverId = $chat->users()->where('user_id', '!=', auth()->id())->first()->id;
        broadcast(new MessageSent($message, $receiverId));


        return $message->load('user', 'replyMessage');

    }


    public function forward(Request $request)
    {
        $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:messages,id',
            'target_chat_ids' => 'required|array',
            'target_chat_ids.*' => 'exists:chats,id',
        ]);

        $userId = auth()->id();

        $messages = Message::whereIn('id', $request->message_ids)->get();
        $newMessages = [];

        foreach ($request->target_chat_ids as $chatId) {

            $chat = Chat::findOrFail($chatId);

            foreach ($messages as $message) {

                // Skip deleted messages
                if ($message->is_deleted) {
                    continue;
                }

                $newMessage = Message::create([
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'type' => $message->type,
                    'body' => $message->body,
                    'file_path' => $message->file_path,
                    'forwarded_from' => $message->id,
                    'is_delivered' => $request->is_delivered,
                    'is_seen' => $request->is_seen,
                ]);

                $newMessages[] = $newMessage;

                // 🔥 Broadcast to receiver
                $receiver = $chat->users()
                    ->where('user_id', '!=', $userId)
                    ->first();

                if ($receiver) {
                    broadcast(new MessageSent($newMessage, $receiver->id));
                }
            }
        }

        return response()->json([
            'status' => 'forwarded',
            'messages' => $newMessages
        ]);
    }


    public function markDelivered(Request $request)
    {
        $user = $request->user();

        $chatIds = $user->chats()->pluck('chats.id');

        Message::whereIn('chat_id', $chatIds)
            ->where('user_id', '!=', $user->id)
            ->where('is_delivered', false)
            ->where('is_seen', false)
            ->update([
                'is_delivered' => true,
                'delivered_at' => now(),
            ]);

        return response()->json(['status' => 'ok']);
    }

    public function markAsSeen(Request $request, $chatId)
    {

        $user = $request->user();

        Message::where('chat_id', $chatId)
            ->where('user_id', '!=', $user->id)
            ->where('is_seen', false)
            ->update([
                'is_seen' => true,
                'seen_at' => now(),
            ]);

        return response()->json(['status' => 'ok']);

    }

    public function deleteMultiple(Request $request)
    {
        $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:messages,id',
            'type' => 'required|in:me,everyone',
        ]);

        $userId = auth()->id();
        $messages = Message::whereIn('id', $request->message_ids)->get();

        foreach ($messages as $message) {

            // DELETE FOR EVERYONE
            if ($request->type === 'everyone') {

                // Only sender allowed
                if ($message->user_id !== $userId) {
                    continue;
                }

                // Delete file if exists
                if ($message->file_path) {
                    Storage::disk('public')->delete($message->file_path);
                }

                $message->update([
                    'is_deleted' => true,
                    'body' => null,
                    'file_path' => null,
                ]);
            }

            // DELETE FOR ME
            if ($request->type === 'me') {

                $deletedFor = $message->deleted_for ?? [];

                if (!in_array($userId, $deletedFor)) {
                    $deletedFor[] = $userId;
                }

                $message->update([
                    'deleted_for' => $deletedFor
                ]);
            }
        }

        return response()->json(['status' => 'deleted']);
    }



    public function share(Request $request)
    {
        $request->validate([
            'chat_ids' => 'required|array|min:1',
            'chat_ids.*' => 'exists:chats,id',
            'type' => 'required|string',
            'body' => 'nullable|string',
            'file' => 'nullable|file|max:20480',
            'reply_to' => 'nullable|exists:messages,id',
            'forwarded_from' => 'nullable|exists:messages,id',
        ]);

        DB::beginTransaction();

        try {
            $path = null;
            $type = $request->type;

            // ✅ upload file ONCE
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $path = $file->store('chat_files', 'public');

                $mime = $file->getMimeType();

                if (str_contains($mime, 'image')) {
                    $type = 'image';
                } elseif (str_contains($mime, 'video')) {
                    $type = 'video';
                } else {
                    $type = 'file';
                }
            }

            $messages = [];

            // ✅ create message per chat
            foreach ($request->chat_ids as $chatId) {

                $message = Message::create([
                    'chat_id' => $chatId,
                    'user_id' => auth()->id(),
                    'type' => $type,
                    'body' => $request->body,
                    'file_path' => $path,
                    'reply_to' => $request->reply_to,
                    'forwarded_from' => $request->forwarded_from,
                    'is_delivered' => false,
                    'is_seen' => false,
                ]);

                // ✅ broadcast to other user
                $chat = Chat::with('users:id')->findOrFail($chatId);

                foreach ($chat->users as $user) {
                    if ($user->id !== auth()->id()) {
                        broadcast(new MessageSent($message->load('user', 'replyMessage'), $user->id));
                    }
                }

                $messages[] = $message->load('user', 'replyMessage');
            }

            DB::commit();

            return response()->json([
                'messages' => $messages
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

}
