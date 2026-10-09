@extends('legal.layout')

@section('title', 'Privacy Policy')

@section('content')
    @php($email = config('app.contact_email'))

    <p>
        Pomopensource is a free, open-source pomodoro timer, available as a website and as an
        Activity inside Discord. This policy explains what data it handles, why, and what you can
        do about it. The short version: it keeps only what it needs to run your timer and show your
        stats, never sells or shares it for advertising, and uses no analytics or tracking.
    </p>

    <h2>Who is responsible</h2>
    <p>
        Pomopensource is the data controller. For any question or request about your data, write to
        <a href="mailto:{{ $email }}">{{ $email }}</a>.
    </p>

    <h2>What we collect</h2>

    <p><strong>When you use the Discord Activity.</strong> Starting the Activity asks Discord for two permissions:</p>
    <ul>
        <li>
            <code>identify</code>: gives us your Discord user ID and your display name (or username).
            We store both to create your Pomopensource account and recognise you next time. We do
            not receive your email address, and we do not store your Discord access token.
        </li>
        <li>
            <code>rpc.activities.write</code>: lets the Activity show your timer in your Discord
            status, such as "Focusing" with the time left and how many others are in the session.
            Your project names and notes are never put in your status.
        </li>
    </ul>
    <p>
        The Activity shows the names and avatars of the people in the call. Discord provides
        them to the app in your call, and we do not store them.
    </p>
    <p>
        In a call, the shared timer is saved on our server under the Discord session's identifier:
        the timer's type, state and durations, and no information about who is in the call. It is
        deleted automatically about a day after the call ends.
    </p>

    <p><strong>When you create an account on the website.</strong> Your name, your email address, and your password, which is stored only as a secure hash.</p>

    <p><strong>What you create while signed in</strong>, on the website or in the Activity:</p>
    <ul>
        <li>your projects (their names);</li>
        <li>
            your focus sessions: when each one started and ended, how long you focused, which
            project it was for, and the note you added, if any;
        </li>
        <li>your settings (timer lengths, sounds, theme);</li>
        <li>statistics computed from your sessions (total time, days active, streak).</li>
    </ul>
    <p>Notes are free text: please don't put sensitive personal information in them.</p>

    <p><strong>Without an account.</strong>
        If you use the timer without signing in, your projects, sessions and stats are kept only
        in your browser's local storage, and are never sent to our server. Your settings are kept
        in your browser session on our server.
    </p>

    <p><strong>Technical data.</strong>
        A session cookie keeps you signed in. While your session is active, our server stores the
        IP address and browser user agent it was opened from, to keep the session secure. Like
        most web servers, it also logs requests (IP address, page requested, browser) to keep the
        service running and investigate problems. These logs are kept only briefly. We use no
        advertising or analytics cookies.
    </p>

    <h2>Why we use it</h2>
    <ul>
        <li>
            <strong>To provide the service you ask for</strong>: signing you in, saving your
            projects, sessions and settings, running the shared timer and showing your stats
            (GDPR art. 6(1)(b), performance of a contract).
        </li>
        <li>
            <strong>To keep the service secure and working</strong>: session security and server
            logs (GDPR art. 6(1)(f), legitimate interest).
        </li>
    </ul>
    <p>We do not sell your data, use it for advertising, or make automated decisions about you.</p>

    <h2>Who else sees it</h2>
    <ul>
        <li>
            <strong>Discord</strong>, when you use the Activity: sign-in goes through Discord, and
            Discord handles your status and the call. Discord's own
            <a href="https://discord.com/privacy">privacy policy</a> applies to that.
        </li>
        <li>
            <strong>Our hosting provider</strong>, which runs the server and database the service
            uses, and processes data only to run them.
        </li>
    </ul>
    <p>We share nothing else, unless the law requires it.</p>

    <h2>How long we keep it</h2>
    <ul>
        <li>Your account and what you created: until you delete your account, or ask us to.</li>
        <li>Shared timers: about a day after the call ends.</li>
        <li>Sign-in sessions: until they expire or you sign out.</li>
        <li>Server logs: briefly, for operating the service.</li>
    </ul>

    <h2>Your rights</h2>
    <p>
        Under the GDPR, you can ask to access, correct, export or delete your data, and you can
        object to or restrict how it is used. To do so, write to
        <a href="mailto:{{ $email }}">{{ $email }}</a>. If you signed in with Discord, tell us your
        Discord username so we can find your account. We reply within one month.
    </p>
    <p>
        If you created an account with an email and password, you can also delete it yourself from
        your profile page. Deleting an account removes your projects, sessions, settings and stats.
    </p>
    <p>
        If you think we have not respected your rights, you can complain to the French data
        protection authority, the <a href="https://www.cnil.fr/">CNIL</a>, or to the authority
        where you live.
    </p>

    <h2>Children</h2>
    <p>
        Pomopensource is not meant for children under 13, or under the minimum age to use
        Discord in your country. If you think a child has given us their data, contact us and we
        will delete it.
    </p>

    <h2>Changes</h2>
    <p>
        If this policy changes, the date at the top of this page changes with it. The history of
        every change is public in the project's
        <a href="https://github.com/corentin-dupaigne/pomopensource">source repository</a>.
    </p>
@endsection
