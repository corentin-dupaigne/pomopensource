@extends('legal.layout')

@section('title', 'Terms of Service')

@section('content')
    <p>These terms apply to Pomopensource, on the web and as a Discord Activity, run by Corentin Dupaigne. By using it, you accept them.</p>

    <h2>The service</h2>
    <p>Pomopensource is a free, open-source pomodoro timer. It is provided "as is", without warranty of any kind, and may change or stop at any time. To the extent the law allows, we are not liable for any loss arising from its use.</p>

    <h2>Your use</h2>
    <p>Do not misuse Pomopensource: no attacks, overloading, automated abuse or illegal use. In Discord, <a href="https://discord.com/terms">Discord's Terms of Service</a> also apply. We may suspend accounts that break these terms.</p>

    <h2>Your content</h2>
    <p>Your projects, sessions and notes remain yours. You let us store and process them only to run the service, as described in the <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>

    <h2>Changes and contact</h2>
    <p>We may update these terms; the date above shows the latest version. For questions, open an issue on <a href="https://github.com/corentin-dupaigne/pomopensource/issues">GitHub</a>.</p>
@endsection
