<?php

namespace App\Http\Controllers;

use App\Services\Discord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiscordActivityController extends Controller
{
    /**
     * Exchange the OAuth2 code from the Embedded App SDK's authorize command
     * for an access token, and sign the Discord user in.
     *
     * The client returns the access token to the SDK's authenticate command.
     * The account's projects come back too, so the Activity can show them
     * without reloading the page.
     */
    public function token(Request $request, Discord $discord)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255',
        ]);

        if (!$discord->configured()) {
            return response()->json(['message' => 'Discord Activity is not configured.'], 503);
        }

        // Activities need no redirect_uri for the code exchange.
        $accessToken = $discord->accessToken($validated['code']);
        if (!$accessToken) {
            return response()->json(['message' => 'Discord rejected the authorization code.'], 401);
        }

        $profile = $discord->profile($accessToken);
        if (!$profile) {
            return response()->json(['message' => 'Could not fetch the Discord profile.'], 502);
        }

        $user = $discord->accountFor($profile);

        $loggedIn = Auth::id() !== $user->id;
        if ($loggedIn) {
            Auth::login($user, remember: true);
            $request->session()->regenerate();
        }

        return response()->json([
            'access_token' => $accessToken,
            'logged_in' => $loggedIn,
            'user' => ['name' => $user->name],
            'projects' => $user->projects()->get(),
        ]);
    }

    /**
     * Whether the session cookie set by token() came back. Inside Discord's
     * iframe the browser may block it, and the Activity then tells the user
     * that nothing is saved to their account.
     */
    public function session()
    {
        return response()->json(['authenticated' => Auth::check()]);
    }
}
