@extends('legal.layout')

@section('title', 'Privacy Policy')
@section('updated', 'October 24, 2026')

@section('content')
    <p>Pomopensource is an open-source pomodoro timer, available at <a href="{{ url('/') }}">{{ parse_url(url('/'), PHP_URL_HOST) }}</a> and as a Discord Activity. It is run by Corentin Dupaigne. Its <a href="https://github.com/corentin-dupaigne/pomopensource">source code</a> is public.</p>

    <h2>What we collect</h2>
    <ul>
        <li><strong>Without an account:</strong> your projects, focus sessions and stats stay in your browser. Settings you change are kept in your session on our server.</li>
        <li><strong>Website account:</strong> your name, email address and password (stored hashed).</li>
        <li><strong>Discord:</strong> when you open the Activity, Discord shares your Discord user ID and display name with us (the <em>identify</em> permission). Your avatar is loaded from Discord and not stored.</li>
        <li><strong>What you create:</strong> projects, focus sessions (start and end times, duration, project, an optional note and, in the Activity, the ID of the server whose call it was in) and settings.</li>
        <li><strong>Shared timer:</strong> for each Discord call using the Activity, the call's ID, the timer's state, the display name of whoever last changed its durations, and which accounts used it.</li>
        <li><strong>Usage statistics:</strong> for each Discord call using the Activity, the IDs of its server and voice channel (or that it was a private call), when it started, and how many accounts used its timer. Which accounts they were is not kept here.</li>
        <li><strong>Technical data:</strong> a cookie keeps you signed in, and your IP address and browser type are stored with that session. Our server logs record IP addresses to keep the service running and secure.</li>
    </ul>

    <h2>Your Discord status</h2>
    <p>In the Activity, the <em>activities.write</em> permission lets Pomopensource show your timer in your Discord status, for example "Focusing" with the time left and how many others are in the session. Project names and notes are never shown.</p>

    <h2>How we use it</h2>
    <p>To run Pomopensource: saving your timer, sessions, projects, settings and stats. We also count how it is used, such as accounts, focus time and calls per day, in a private dashboard that only reads the data listed above, without names, emails or notes. We do not show ads, use third-party analytics or trackers, or sell or share your data. Discord handles the data it shares with us under <a href="https://discord.com/privacy">its own privacy policy</a>.</p>

    <h2>Keeping and deleting your data</h2>
    <p>We keep your data while you use Pomopensource. A call's shared timer, with the accounts that used it, is deleted a day after the call ends; its usage statistics are kept. You can delete a website account, and everything in it, from your profile page. To delete an account made through Discord, or any other data, contact us below. Data kept in your browser is removed by clearing your browser's site data.</p>

    <h2>Contact</h2>
    <p>Open an issue on <a href="https://github.com/corentin-dupaigne/pomopensource/issues">GitHub</a>.</p>
@endsection
