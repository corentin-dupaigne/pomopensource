<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Signing in with Discord, from the Activity or the website. Both find the
 * account by its Discord ID, so it holds the same data in both.
 */
class Discord
{
    public function configured(): bool
    {
        return config('services.discord.client_id') && config('services.discord.client_secret');
    }

    /**
     * Exchange an OAuth2 code for an access token, or null when Discord
     * rejects it. The website sends the redirect URI it asked the code
     * with; Activities have none.
     */
    public function accessToken(string $code, ?string $redirectUri = null): ?string
    {
        $response = Http::asForm()->post($this->apiUrl('/oauth2/token'), array_filter([
            'client_id' => config('services.discord.client_id'),
            'client_secret' => config('services.discord.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]));

        return $response->successful() ? $response->json('access_token') : null;
    }

    /**
     * The Discord user the access token belongs to, or null.
     *
     * @return array{id: string, name: string}|null
     */
    public function profile(string $accessToken): ?array
    {
        $response = Http::withToken($accessToken)->get($this->apiUrl('/users/@me'));
        if ($response->failed() || !$response->json('id')) {
            return null;
        }

        return [
            'id' => (string) $response->json('id'),
            'name' => $response->json('global_name') ?? $response->json('username'),
        ];
    }

    /**
     * The account of a Discord user, created on their first sign-in.
     *
     * @param  array{id: string, name: string}  $profile
     */
    public function accountFor(array $profile): User
    {
        $user = User::firstOrNew(['discord_id' => $profile['id']]);
        $user->name = $profile['name'];
        $user->save();

        return $user;
    }

    private function apiUrl(string $path): string
    {
        return config('services.discord.api_url').$path;
    }
}
