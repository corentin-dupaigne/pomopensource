<?php

use App\Models\Setting;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    // Send cookies with JSON requests too, as the browser does.
    $this->withCredentials();
    $this->seed(SettingsSeeder::class);
    $this->pomodoro = Setting::firstWhere('key', 'pomodoro_duration');
    $this->theme = Setting::firstWhere('key', 'theme');
});

function settingValue($response, string $key)
{
    return collect($response->json())->flatMap(fn ($category) => $category['settings'])->firstWhere('key', $key)['value'];
}

test('a guest\'s settings outlast the session', function () {
    $saved = $this->patchJson('/user-settings', ['settings' => [
        ['id' => $this->pomodoro->id, 'value' => '50'],
        ['id' => $this->theme->id, 'value' => 'Rainy Window'],
    ]])->assertOk();
    $cookie = $saved->getCookie('guest_settings');
    expect($cookie)->not->toBeNull()
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addYear()->getTimestamp());

    // The session expired: only the browser's cookies come back.
    $this->flushSession();
    $this->withCookie('guest_settings', $cookie->getValue());

    expect(settingValue($this->getJson('/user-settings'), 'pomodoro_duration'))->toBe('50');
    $this->get('/background')->assertSee('Rainy Window');
});

test('saving again keeps the other settings', function () {
    $first = $this->patchJson('/user-settings', ['settings' => [['id' => $this->pomodoro->id, 'value' => '50']]]);
    $this->withCookie('guest_settings', $first->getCookie('guest_settings')->getValue());

    $second = $this->patchJson('/user-settings', ['settings' => [['id' => $this->theme->id, 'value' => 'Rainy Window']]]);
    $this->flushSession();
    $this->withCookie('guest_settings', $second->getCookie('guest_settings')->getValue());

    expect(settingValue($this->getJson('/user-settings'), 'pomodoro_duration'))->toBe('50');
});

test('settings saved in the session before still count', function () {
    $this->withSession(['user_settings' => [$this->pomodoro->id => '45']]);

    expect(settingValue($this->getJson('/user-settings'), 'pomodoro_duration'))->toBe('45');
});
