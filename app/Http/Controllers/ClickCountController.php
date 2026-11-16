<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Counts how often each part of the interface is opened, to see what
 * people use. Anonymous: a count per day, element and context, never who.
 */
class ClickCountController extends Controller
{
    // What is counted. "app_open" is each load of the app: what the rest
    // is compared with.
    public const ELEMENTS = [
        'app_open',
        'stats',
        'stats_detail',
        'settings',
        'leaderboard',
        'fullscreen',
        'manage_subjects',
    ];

    public const CONTEXTS = ['activity', 'website'];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'element' => ['required', Rule::in(self::ELEMENTS)],
            'context' => ['required', Rule::in(self::CONTEXTS)],
        ]);

        DB::table('click_counts')->upsert(
            [['day' => now()->toDateString(), ...$validated, 'clicks' => 1]],
            ['day', 'context', 'element'],
            ['clicks' => DB::raw('click_counts.clicks + 1')],
        );

        return response()->noContent();
    }
}
