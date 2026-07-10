<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') === 'Laravel' ? 'MediFlow' : config('app.name', 'MediFlow') }}</title>

        <link rel="icon" href="{{ asset('brand/favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" href="{{ asset('brand/favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('brand/favicon.png') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php
        $layoutUser = auth()->user();
        $layoutRole = $layoutUser && method_exists($layoutUser, 'getRoleNames') ? ($layoutUser->getRoleNames()->first() ?: 'sin_rol') : 'guest';
        $layoutClinicId = $layoutUser?->activeClinicId();
    @endphp
    <body
        class="bg-[#F8FAFC] font-sans text-[#0F172A] antialiased"
        data-user-id="{{ $layoutUser?->id ?? 'guest' }}"
        data-active-clinic-id="{{ $layoutClinicId ?? 'none' }}"
        data-user-role="{{ $layoutRole }}"
    >
        <div x-data="{ sidebarOpen: false }" class="min-h-screen">
            @include('layouts.navigation')

            <div id="connection-toast-container" class="fixed right-4 top-20 z-[80] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-3"></div>

            <div class="lg:pl-72">
                <main class="min-h-screen pt-20">
                    <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        @isset($header)
                            <div class="mb-6 border-b border-[#E2E8F0] pb-5">
                                {{ $header }}
                            </div>
                        @endisset

                        {{ $slot }}
                    </div>
                </main>
            </div>

            @auth
                <x-mediflow-assistant />
            @endauth
        </div>
    </body>
</html>
