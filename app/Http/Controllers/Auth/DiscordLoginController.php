<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Discord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Signing in on the website with Discord. The account is the one the
 * Activity uses for the same Discord user, so both show the same data.
 */
class DiscordLoginController extends Controller
{
    public function redirect(Request $request, Discord $discord): RedirectResponse
    {
        abort_unless($discord->configured(), 404);

        // Ties Discord's answer to this browser, against forged callbacks.
        $state = Str::random(40);
        $request->session()->put('discord_oauth_state', $state);

        return redirect()->away($discord->authorizeUrl(route('discord.callback'), $state));
    }

    public function callback(Request $request, Discord $discord): RedirectResponse
    {
        $state = $request->session()->pull('discord_oauth_state');
        if (!$state || !hash_equals($state, (string) $request->query('state'))) {
            return $this->failed('Signing in with Discord took too long or was started elsewhere. Please try again.');
        }

        if ($request->query('error') || !$request->query('code')) {
            return $this->failed('Signing in with Discord was cancelled.');
        }

        $accessToken = $discord->accessToken((string) $request->query('code'), route('discord.callback'));
        $profile = $accessToken ? $discord->profile($accessToken) : null;
        if (!$profile) {
            return $this->failed('Discord could not confirm who you are. Please try again.');
        }

        Auth::login($discord->accountFor($profile), remember: true);
        $request->session()->regenerate();

        return redirect('/');
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['discord' => $message]);
    }
}
