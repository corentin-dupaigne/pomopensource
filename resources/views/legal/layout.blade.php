<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title') — Pomopensource</title>
    <link rel="icon" href="{{ asset('favicon-32x32.png') }}" sizes="32x32" type="image/png">
    {{-- Plain styles, no build step: these pages must load for anyone,
         including Discord's reviewers, without the app's JavaScript. --}}
    <style>
        :root {
            color-scheme: dark;
            --bg: #111214;
            --text: #e8e8ea;
            --muted: #a3a3ab;
            --rule: #2a2b30;
            --link: #9db4ff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font: 16px/1.65 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        main { max-width: 44rem; margin: 0 auto; padding: 3rem 1rem 4rem; }
        nav { display: flex; gap: 1.25rem; margin-bottom: 2.5rem; font-size: 0.875rem; }
        nav a { color: var(--muted); text-decoration: none; }
        nav a:hover, nav a[aria-current] { color: var(--text); }
        h1 { font-size: 2rem; line-height: 1.2; margin: 0 0 0.25rem; }
        .updated { color: var(--muted); font-size: 0.875rem; margin: 0 0 2rem; }
        h2 { font-size: 1.2rem; margin: 2.25rem 0 0.5rem; padding-top: 1.5rem; border-top: 1px solid var(--rule); }
        p, ul { margin: 0 0 1rem; }
        ul { padding-left: 1.25rem; }
        li { margin-bottom: 0.4rem; }
        a { color: var(--link); }
        strong { color: #fff; }
    </style>
</head>
<body>
<main>
    <nav aria-label="Pages">
        <a href="{{ url('/') }}">Pomopensource</a>
        <a href="{{ route('privacy') }}" @if (request()->routeIs('privacy')) aria-current="page" @endif>Privacy</a>
        <a href="{{ route('terms') }}" @if (request()->routeIs('terms')) aria-current="page" @endif>Terms</a>
    </nav>

    <h1>@yield('title')</h1>
    <p class="updated">Last updated: 9 October 2026</p>

    @yield('content')
</main>
</body>
</html>
