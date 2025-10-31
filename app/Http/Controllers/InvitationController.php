<?php

namespace App\Http\Controllers;

use App\Models\InvitationToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    /**
     * Verify an invitation token.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|string|email',
        ]);

        $token = InvitationToken::where('token', $request->token)
            ->where('email', $request->email)
            ->where('expires_at', '>', now())
            ->first();

        if (!$token) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired invitation token'
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'email' => $token->email,
            'role' => $token->role
        ]);
    }

    /**
     * Create a new invitation.
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|max:255|unique:users',
            'role' => 'required|string|in:admin,guest',
        ]);

        $token = InvitationToken::create([
            'email' => $request->email,
            'token' => Str::random(32),
            'role' => $request->role,
            'expires_at' => now()->addDays(7),
        ]);

        $invitationUrl = url('/register?token=' . $token->token . '&email=' . urlencode($token->email));

        return response()->json([
            'invitation' => $token,
            'url' => $invitationUrl
        ], 201);
    }
}
