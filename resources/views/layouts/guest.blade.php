<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    @vite('resources/css/app.css')
</head>
<body class="font-sans text-gray-900 antialiased">
<div class="relative min-h-screen flex flex-col items-center justify-center bg-cover bg-center" style="background-image: url('{{ asset('images/backgrounds/lofi_cafe.webp') }}');">
    <div class="absolute inset-0 bg-black/50"></div>

    <a href="/" class="z-10 mb-4 flex items-center gap-2 text-sm text-white/60 hover:text-white transition">
        <i class="fas fa-arrow-left text-xs" aria-hidden="true"></i>
        Back to app
    </a>

    <div class="z-10 w-full max-w-md mx-3 sm:mx-auto px-5 py-6 sm:px-8 bg-white/10 backdrop-blur-lg rounded-xl shadow-xl">
        {{ $slot }}
    </div>

    <p class="z-10 mt-4 flex gap-4 text-xs text-white/50">
        <a href="{{ route('privacy') }}" class="hover:text-white transition">Privacy</a>
        <a href="{{ route('terms') }}" class="hover:text-white transition">Terms</a>
    </p>
</div>
</body>
</html>
