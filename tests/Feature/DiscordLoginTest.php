<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.discord.client_id' => 'client-id',
        'services.discord.client_secret' => 'client-secret',
    ]);
});

function fakeDiscordOAuth(array $profile = ['id' => '1234', 'username' => 'pomo', 'global_name' => 'Pomo Fan']): void
{
    Http::fake([
        'discord.com/api/oauth2/token' => Http::response(['access_token' => 'discord-token']),
        'discord.com/api/users/@me' => Http::response($profile),
    ]);
}

/**
 * Start signing in with Discord, and return the state Discord would send back.
 */
function startDiscordLogin(): string
{
    $location = test()->get('/auth/discord')->assertRedirect()->headers->get('Location');
    parse_str(parse_url($location, PHP_URL_QUERY), $query);

    return $query['state'];
}

test('the login and register pages offer to continue with Discord', function () {
    $this->get('/login')->assertSee('Continue with Discord')->assertSee(route('discord.redirect'));
    $this->get('/register')->assertSee('Continue with Discord');
});

test('without a Discord application, nothing offers it', function () {
    config(['services.discord.client_id' => null]);

    $this->get('/login')->assertDontSee('Continue with Discord');
    $this->get('/auth/discord')->assertNotFound();
});

test('signing in sends the user to Discord to authorize the app', function () {
    $location = $this->get('/auth/discord')->assertRedirect()->headers->get('Location');

    expect($location)->toStartWith('https://discord.com/api/oauth2/authorize?');
    parse_str(parse_url($location, PHP_URL_QUERY), $query);
    expect($query)->toMatchArray([
        'client_id' => 'client-id',
        'response_type' => 'code',
        'scope' => 'identify',
        'redirect_uri' => route('discord.callback'),
    ])->and($query['state'])->toHaveLength(40);
});

test('coming back from Discord signs the user in', function () {
    fakeDiscordOAuth();
    $state = startDiscordLogin();

    $this->get("/auth/discord/callback?code=abc&state={$state}")->assertRedirect('/');

    $user = User::firstWhere('discord_id', '1234');
    expect($user->name)->toBe('Pomo Fan');
    $this->assertAuthenticatedAs($user);
    Http::assertSent(fn ($request) => $request->url() === 'https://discord.com/api/oauth2/token'
        && $request['code'] === 'abc'
        && $request['redirect_uri'] === route('discord.callback'));
});

test('the website signs in to the account used in the Activity', function () {
    $user = User::create(['discord_id' => '1234', 'name' => 'Pomo Fan']);
    $user->projects()->create(['name' => 'Thesis']);
    fakeDiscordOAuth();

    $this->get('/auth/discord/callback?code=abc&state='.startDiscordLogin());

    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1);
    $this->get('/')->assertInertia(fn ($page) => $page->where('projects.0.name', 'Thesis'));
});

test('a callback that this browser did not start is refused', function () {
    fakeDiscordOAuth();
    startDiscordLogin();

    $this->get('/auth/discord/callback?code=abc&state=forged')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('discord');

    $this->assertGuest();
    Http::assertNothingSent();
});

test('a state is used once', function () {
    fakeDiscordOAuth();
    $state = startDiscordLogin();
    $this->get("/auth/discord/callback?code=abc&state={$state}");
    auth()->logout();

    $this->get("/auth/discord/callback?code=abc&state={$state}")->assertSessionHasErrors('discord');
    $this->assertGuest();
});

test('declining on Discord comes back to the login page', function () {
    $state = startDiscordLogin();

    $this->get("/auth/discord/callback?error=access_denied&state={$state}")
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('discord');
    $this->assertGuest();
});

test('a code Discord rejects does not sign in', function () {
    Http::fake(['discord.com/api/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    $this->get('/auth/discord/callback?code=abc&state='.startDiscordLogin())
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('discord');
    $this->assertGuest();
});
