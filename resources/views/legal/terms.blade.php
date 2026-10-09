@extends('legal.layout')

@section('title', 'Terms of Service')

@section('content')
    @php($email = config('app.contact_email'))

    <p>
        These terms apply when you use Pomopensource, the pomodoro timer available as a website and
        as an Activity inside Discord (the "service"). By using the service, you accept them. If you
        don't, please don't use it.
    </p>

    <h2>The service</h2>
    <p>
        Pomopensource is free. It lets you time focus sessions and breaks, group them by project,
        add notes, see your statistics, and share one timer with the people in a Discord call. Its
        source code is published under the MIT licence; these terms cover the hosted service, not
        your use of the code.
    </p>

    <h2>Your account</h2>
    <p>
        You can use the timer without an account. To keep your data across devices, you sign in
        with Discord in the Activity, or create an account on the website. You are responsible for
        what happens under your account, so keep your password to yourself. When you use the
        Activity, Discord's <a href="https://discord.com/terms">Terms of Service</a> and
        <a href="https://discord.com/guidelines">Community Guidelines</a> apply too.
    </p>
    <p>You must be at least 13, and old enough to use Discord where you live.</p>

    <h2>Acceptable use</h2>
    <p>Don't:</p>
    <ul>
        <li>use the service to break the law or infringe anyone's rights;</li>
        <li>try to access other people's accounts or data, or get around the service's security;</li>
        <li>overload, disrupt or scrape the service, for example with automated requests;</li>
        <li>put unlawful, abusive or other people's personal content in project names or notes.</li>
    </ul>
    <p>
        If you do, we may suspend or delete your account. Where it is reasonable, we will tell you
        first.
    </p>

    <h2>Your content</h2>
    <p>
        Your projects, sessions and notes stay yours. You allow us to store and process them only
        to provide the service to you, as described in the
        <a href="{{ route('privacy') }}">privacy policy</a>. You can delete them, or your whole
        account, at any time.
    </p>

    <h2>Availability</h2>
    <p>
        We run Pomopensource on a best-effort basis. It may be interrupted, changed, or stopped
        altogether, and features may come and go. If we decide to shut the service down, we will
        try to give notice so you can get your data first.
    </p>

    <h2>No warranty and liability</h2>
    <p>
        The service is provided "as is", without any warranty that it will be uninterrupted,
        error-free, or keep your data forever. Keep your own copy of anything important.
    </p>
    <p>
        As far as the law allows, we are not liable for indirect damage, or for lost data, time or
        profit, resulting from your use of the service. Nothing in these terms limits liability
        that cannot be limited by law, or your rights as a consumer.
    </p>

    <h2>Ending</h2>
    <p>
        You can stop using the service whenever you like, and delete your account from your profile
        page or by writing to us. These terms then stop applying to you.
    </p>

    <h2>Changes</h2>
    <p>
        We may update these terms. The date at the top of this page shows the latest version, and
        the history of every change is public in the project's
        <a href="https://github.com/corentin-dupaigne/pomopensource">source repository</a>. If you
        keep using the service after a change, you accept the new terms.
    </p>

    <h2>Law and disputes</h2>
    <p>
        These terms are governed by French law. If a dispute arises, please contact us first so we
        can try to settle it. If that fails, the French courts have jurisdiction, without
        affecting your right as a consumer to go to the courts where you live.
    </p>

    <h2>Contact</h2>
    <p>Questions about these terms: <a href="mailto:{{ $email }}">{{ $email }}</a>.</p>
@endsection
