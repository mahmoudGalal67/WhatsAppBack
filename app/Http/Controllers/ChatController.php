<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function openChat(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $authId = auth()->id();
        $otherId = $request->user_id;

        // Check if chat already exists between both users
        $chat = Chat::whereHas('users', fn($q) => $q->where('user_id', $authId))
            ->whereHas('users', fn($q) => $q->where('user_id', $otherId))
            ->where('is_group', false)
            ->first();

        if (!$chat) {
            $chat = Chat::create(['is_group' => false]);

            $chat->users()->attach([
                $authId => ['last_read_at' => now()],
                $otherId => ['last_read_at' => null],
            ]);
        }

        return $chat->load('users:id,name,avatar,status');
    }


    public function createPrivateChat(Request $request)
    {
        $request->validate([
            'user_one_id' => 'required|exists:users,id',
            'other_user_ids' => 'required|array|min:1',
            'other_user_ids.*' => 'exists:users,id',
        ]);

        $userOne = $request->user_one_id;
        $otherUsers = $request->other_user_ids;

        // Prevent self-chat
        if (in_array($userOne, $otherUsers)) {
            return response()->json([
                'message' => 'User cannot chat with himself'
            ], 422);
        }

        return DB::transaction(function () use ($userOne, $otherUsers) {

            $createdChats = [];

            foreach ($otherUsers as $otherUser) {
                // 🔍 Check if private chat already exists
                $existingChat = Chat::where('is_group', false)
                    ->whereHas('users', function ($q) use ($userOne) {
                        $q->where('user_id', $userOne);
                    })
                    ->whereHas('users', function ($q) use ($otherUser) {
                        $q->where('user_id', $otherUser);
                    })
                    ->withCount('users')
                    ->having('users_count', 2) // make sure only 2 users
                    ->first();

                if ($existingChat) {
                    $createdChats[] = $existingChat->load('users');
                    continue;
                }
                $chat = Chat::create([
                    'is_group' => false,
                ]);

                $chat->users()->attach([$userOne, $otherUser]);

                $createdChats[] = $chat->load('users');
            }

            return response()->json($createdChats, 201);
        });
    }


    public function index()
    {
        $user = auth()->user();

        $chats = $user->chats()
            ->with(['users:id,name,phone_number,avatar,status'])
            ->with([
                'messages' => function ($q) {
                    $q->latest()->limit(1);
                }
            ])
            ->get()
            ->map(function ($chat) use ($user) {
                $lastMessage = $chat->messages->first();

                $unreadCount = Message::where('chat_id', $chat->id)
                    ->where('user_id', '!=', $user->id)
                    ->where('is_seen', false)
                    ->count();

                return [
                    'id' => $chat->id,
                    'is_group' => $chat->is_group,
                    'users' => $chat->users,
                    'last_message' => $lastMessage,
                    'unread_count' => $unreadCount,
                    'updated_at' => $chat->updated_at,
                ];
            })
            ->sortByDesc('last_message.created_at')
            ->values();

        return $chats;
    }
    public function messages($chatId)
    {
        $chat = Chat::findOrFail($chatId);

        abort_unless($chat->users->contains(auth()->id()), 403);

        $messages = Message::where('chat_id', $chatId)
            ->with(['user:id,name,avatar', 'replyMessage'])
            ->latest()
            ->paginate(30);

        return $messages;
    }

    public function deleteMultipleChats(Request $request)
    {
        $request->validate([
            'chat_ids' => 'required|array|min:1',
            'chat_ids.*' => 'exists:chats,id',
        ]);

        $chatIds = $request->chat_ids;

        DB::transaction(function () use ($chatIds) {
            Chat::whereIn('id', $chatIds)->delete();
        });

        return response()->json([
            'message' => 'Chats deleted successfully',
            'deleted_chat_ids' => $chatIds
        ]);
    }
    public function markAsRead($chatId)
    {
        $chat = Chat::findOrFail($chatId);

        $chat->users()->updateExistingPivot(auth()->id(), [
            'last_read_at' => now()
        ]);

        return response()->json(['status' => 'updated']);
    }
    public function createGroup(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'users' => 'required|array|min:2'
        ]);

        $chat = Chat::create([
            'name' => $request->name,
            'is_group' => true
        ]);

        $chat->users()->attach(
            collect($request->users)
                ->push(auth()->id())
                ->unique()
                ->mapWithKeys(fn($id) => [$id => ['last_read_at' => null]])
        );

        return $chat->load('users:id,name,avatar');
    }

}


