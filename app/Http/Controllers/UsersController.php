<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UsersController extends Controller
{
    public function index()
    {
        return response()->json(User::all());
    }
    public function editProfile(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string|max:255',
            'name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|max:20',
            'status' => 'sometimes|string|max:255',
            'avatar' => 'sometimes|image|mimes:jpg,jpeg,png,webp|max:51200',
        ]);

        $user = User::findOrFail($request->id);
        // if ($user->id != auth()->id()) {
        //     return response()->json([
        //         'message' => 'You are not authorized to update this user',
        //     ], 403);
        // }
        // ✅ Update basic fields
        $user->fill($validated);

        // ✅ Handle avatar upload
        if ($request->hasFile('avatar')) {

            // delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // store new avatar
            $path = $request->file('avatar')->store('avatars', 'public');

            $user->avatar = $path;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }
}
