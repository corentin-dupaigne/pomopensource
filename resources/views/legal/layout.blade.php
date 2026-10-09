<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Pomopensource</title>
    <style>
        body { margin: 0; background: #111; color: #e5e5e5; font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, Ubuntu, sans-serif; }
        main { max-width: 42rem; margin: 0 auto; padding: 3rem 1.25rem; }
        h1 { margin: 0 0 .25rem; color: #fff; font-size: 2rem; }
        h2 { margin: 2rem 0 .5rem; color: #fff; font-size: 1.15rem; }
        p, li { color: #d4d4d4; }
        ul { padding-left: 1.25rem; }
        a { color: #93c5fd; }
        .updated { margin: 0; color: #a3a3a3; font-size: .9rem; }
    </style>
</head>
<body>
<main>
    <h1>@yield('title')</h1>
    <p class="updated">Last updated: October 9, 2026</p>
    @yield('content')
</main>
</body>
</html>
