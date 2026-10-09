<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.discord.client_id' => 'client-id',
        'services.discord.client_secret' => 'client-secret',
    ]);
});

function fakeDiscord(array $profile = ['id' => '1234', 'username' => 'pomo', 'global_name' => 'Pomo Fan']): void
{
    Http::fake([
        'discord.com/api/oauth2/token' => Http::response(['access_token' => 'discord-token']),
        'discord.com/api/users/@me' => Http::response($profile),
    ]);
}

test('a Discord code signs the user in and returns the access token', function () {
    fakeDiscord();

    $response = $this->postJson('/discord/token', ['code' => 'abc']);

    $response->assertOk()->assertJson(['access_token' => 'discord-token', 'logged_in' => true]);
    $user = User::firstWhere('discord_id', '1234');
    expect($user->name)->toBe('Pomo Fan')
        ->and($user->email)->toBeNull();
    $this->assertAuthenticatedAs($user);

    Http::assertSent(fn ($request) => $request->url() === 'https://discord.com/api/oauth2/token'
        && $request['client_secret'] === 'client-secret'
        && $request['code'] === 'abc'
        && $request['grant_type'] === 'authorization_code');
});

test('signing in again reuses the account and does not log in twice', function () {
    $user = User::create(['discord_id' => '1234', 'name' => 'Old name']);
    fakeDiscord();

    $this->actingAs($user)
        ->postJson('/discord/token', ['code' => 'abc'])
        ->assertOk()
        ->assertJson(['logged_in' => false]);

    expect(User::count())->toBe(1)
        ->and($user->fresh()->name)->toBe('Pomo Fan');
});

test('the token response includes the account\'s projects', function () {
    $user = User::create(['discord_id' => '1234', 'name' => 'Pomo Fan']);
    $user->projects()->create(['name' => 'Thesis']);
    fakeDiscord();

    $this->postJson('/discord/token', ['code' => 'abc'])
        ->assertOk()
        ->assertJsonPath('user.name', 'Pomo Fan')
        ->assertJsonPath('projects.0.name', 'Thesis');
});

test('the session endpoint reports whether the sign-in cookie came back', function () {
    $this->getJson('/discord/session')->assertOk()->assertJson(['authenticated' => false]);

    $user = User::create(['discord_id' => '1234', 'name' => 'Pomo Fan']);
    $this->actingAs($user)->getJson('/discord/session')->assertJson(['authenticated' => true]);
});

test('a rejected code returns 401 and signs nobody in', function () {
    Http::fake(['discord.com/api/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    $this->postJson('/discord/token', ['code' => 'bad'])->assertStatus(401);

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('the endpoint reports when Discord is not configured', function () {
    config(['services.discord.client_secret' => null]);
    Http::fake();

    $this->postJson('/discord/token', ['code' => 'abc'])->assertStatus(503);

    Http::assertNothingSent();
});

test('a code is required', function () {
    $this->postJson('/discord/token', [])->assertUnprocessable();
});
