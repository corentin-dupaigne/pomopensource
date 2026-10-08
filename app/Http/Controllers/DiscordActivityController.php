<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class DiscordActivityController extends Controller
{
    /**
     * Exchange the OAuth2 code from the Embedded App SDK's authorize command
     * for an access token, and sign the Discord user in.
     *
     * The client returns the access token to the SDK's authenticate command.
     */
    public function token(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255',
        ]);

        $clientId = config('services.discord.client_id');
        $clientSecret = config('services.discord.client_secret');
        $apiUrl = config('services.discord.api_url');

        if (!$clientId || !$clientSecret) {
            return response()->json(['message' => 'Discord Activity is not configured.'], 503);
        }

        // Activities need no redirect_uri for the code exchange.
        $tokenResponse = Http::asForm()->post("{$apiUrl}/oauth2/token", [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $validated['code'],
        ]);

        if ($tokenResponse->failed()) {
            return response()->json(['message' => 'Discord rejected the authorization code.'], 401);
        }

        $accessToken = $tokenResponse->json('access_token');

        $profileResponse = Http::withToken($accessToken)->get("{$apiUrl}/users/@me");

        if ($profileResponse->failed()) {
            return response()->json(['message' => 'Could not fetch the Discord profile.'], 502);
        }

        $user = User::firstOrNew(['discord_id' => $profileResponse->json('id')]);
        $user->name = $profileResponse->json('global_name') ?? $profileResponse->json('username');
        $user->save();

        $loggedIn = Auth::id() !== $user->id;
        if ($loggedIn) {
            Auth::login($user, remember: true);
            $request->session()->regenerate();
        }

        return response()->json([
            'access_token' => $accessToken,
            'logged_in' => $loggedIn,
        ]);
    }
}
