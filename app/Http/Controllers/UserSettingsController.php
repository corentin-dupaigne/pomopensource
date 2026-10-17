<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SettingCategory;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class UserSettingsController extends Controller
{
    // A guest's settings live in a cookie: the session expires after two
    // hours without a request, and the timer went back to 25 minutes.
    private const GUEST_COOKIE = 'guest_settings';

    private const GUEST_COOKIE_MINUTES = 60 * 24 * 365 * 5;

    public function index(Request $request)
    {
        $categories = SettingCategory::with(['settings' => function ($query) {
            $query->orderBy('display_order');
        }])
            ->orderBy('display_order')
            ->get();

        $settings = $this->getSettings($request);

        $formattedSettings = $categories->map(function ($category) use ($settings) {
            return [
                'name' => $category->name,
                'icon' => $category->icon,
                'settings' => $category->settings->map(function ($setting) use ($settings) {
                    return [
                        'id' => $setting->id,
                        'key' => $setting->key,
                        'name' => $setting->name,
                        'type' => $setting->type,
                        'options' => $setting->options,
                        'value' => $settings[$setting->id] ?? $setting->default_value,
                    ];
                }),
            ];
        });

        return response()->json($formattedSettings);
    }

    public function getBackground(Request $request)
    {
        $settings = $this->getSettings($request);
        $themeSetting = Setting::where('key', 'theme')->first();
        return $settings[$themeSetting->id] ?? $themeSetting->default_value;
    }

    public function update(Request $request)
    {
        $settings = $request->validate([
            'settings' => 'required|array',
            'settings.*.id' => 'required|exists:settings,id',
            'settings.*.value' => 'required',
        ]);

        $response = response()->json(['message' => 'Settings updated successfully']);

        if (Auth::check()) {
            $this->updateAuthenticatedUserSettings(Auth::user(), $settings['settings']);
        } else {
            $response->withCookie($this->guestSettingsCookie($request, $settings['settings']));
        }

        return $response;
    }

    private function getSettings(Request $request)
    {
        if (Auth::check()) {
            return Auth::user()->settings()->pluck('value', 'setting_id')->toArray();
        }

        return $this->guestSettings($request);
    }

    /**
     * A guest's settings, by setting ID. Those saved before the cookie, in
     * the session, still count until the next save.
     */
    private function guestSettings(Request $request): array
    {
        $saved = json_decode((string) $request->cookie(self::GUEST_COOKIE), true);

        return is_array($saved) ? $saved : $request->session()->get('user_settings', []);
    }

    private function updateAuthenticatedUserSettings($user, $settings)
    {
        foreach ($settings as $setting) {
            UserSetting::updateOrCreate(
                ['user_id' => $user->id, 'setting_id' => $setting['id']],
                ['value' => $setting['value']]
            );
        }
    }

    private function guestSettingsCookie(Request $request, $settings)
    {
        $guestSettings = $this->guestSettings($request);
        foreach ($settings as $setting) {
            $guestSettings[$setting['id']] = $setting['value'];
        }

        // Partitioned like the session cookie, which Discord's iframe needs.
        return Cookie::make(self::GUEST_COOKIE, json_encode($guestSettings), self::GUEST_COOKIE_MINUTES)
            ->withPartitioned((bool) config('session.partitioned'));
    }
}
