<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'MediFlow') }}</title>

        <!-- Fonts -->
        <link rel="icon" href="{{ asset('brand/favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" href="{{ asset('brand/favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('brand/favicon.png') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div class="text-center">
                <a href="/" class="inline-flex flex-col items-center gap-3">
                    <img src="{{ asset('brand/mediflow-app-icon.png') }}" alt="MediFlow" class="h-16 w-16 rounded-2xl shadow-sm">
                    <span class="text-2xl font-extrabold tracking-tight text-[#0F172A]">MediFlow</span>
                    <span class="text-sm font-medium text-[#475569]">Sistema de gestión clínica</span>
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
