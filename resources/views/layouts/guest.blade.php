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
<body class="font-sans text-gray-900 antialiased bg-gray-100">
<div class="relative min-h-screen flex flex-col items-center justify-center bg-cover bg-center" style="background-image: url('{{ asset('images/backgrounds/lofi_cafe.webp') }}');">
    <div class="absolute inset-0 bg-black bg-opacity-40"></div>

    <div class="z-10">
        <a href="/">
            <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
        </a>
    </div>

    <div class="z-10 w-full sm:max-w-md mt-6 px-6 py-4 bg-white/10 backdrop-blur-lg rounded-lg shadow-xl overflow-hidden">
        {{ $slot }}
    </div>
</div>
</body>
</html>

<style>
    .bg-cover {
        background-size: cover;
    }

    .bg-center {
        background-position: center;
    }

    .app {
        position: relative;
    }

    /* Additional styles can be added if needed */
</style>
