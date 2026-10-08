<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts (same as the app) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">

    <!-- Fontawesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
          integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
          crossorigin="anonymous" referrerpolicy="no-referrer"/>

    @vite('resources/css/app.css')
</head>
<body class="font-['Inter',sans-serif] text-gray-900 antialiased">
<div class="relative min-h-screen flex flex-col items-center justify-center bg-cover bg-center" style="background-image: url('{{ asset('images/backgrounds/lofi_cafe.webp') }}');">
    <div class="absolute inset-0 bg-black/50"></div>

    <a href="/" class="z-10 mb-4 flex items-center gap-2 text-sm text-white/60 hover:text-white transition">
        <i class="fas fa-arrow-left text-xs" aria-hidden="true"></i>
        Back to app
    </a>

    <div class="z-10 w-full max-w-md mx-3 sm:mx-auto px-5 py-6 sm:px-8 bg-white/10 backdrop-blur-lg rounded-xl shadow-xl">
        {{ $slot }}
    </div>
</div>
</body>
</html>
