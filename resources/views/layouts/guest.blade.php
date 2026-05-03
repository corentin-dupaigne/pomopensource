<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-900 antialiased">
<div class="relative min-h-screen flex flex-col items-center justify-center bg-cover bg-center" style="background-image: url('{{ asset('images/backgrounds/lofi_cafe.webp') }}');">
    <div class="absolute inset-0 bg-black/50"></div>

    <a href="/" class="z-10 mb-4 flex items-center gap-2 text-sm text-white/60 hover:text-white transition">
        <i class="fas fa-arrow-left text-xs"></i>
        Back to app
    </a>

    <div class="z-10 w-full max-w-md mx-3 sm:mx-auto px-5 py-6 sm:px-8 bg-white/10 backdrop-blur-lg rounded-xl shadow-xl">
        {{ $slot }}
    </div>
</div>
</body>
</html>
