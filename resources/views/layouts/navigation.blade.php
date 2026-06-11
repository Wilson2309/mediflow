@php
    $user = Auth::user();
    $roleName = $user && method_exists($user, 'getRoleNames') ? $user->getRoleNames()->first() : null;
    $roleLabel = $roleName ? str($roleName)->replace('_', ' ')->title() : 'Rol provisional';
    $initials = collect(explode(' ', trim($user?->name ?? 'Usuario')))
        ->filter()
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->take(2)
        ->implode('') ?: 'U';

    $icons = [
        'dashboard' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75h6.5v6.5h-6.5v-6.5ZM13.75 3.75h6.5v6.5h-6.5v-6.5ZM3.75 13.75h6.5v6.5h-6.5v-6.5ZM13.75 13.75h6.5v6.5h-6.5v-6.5Z" /></svg>',
        'patients' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0M18.75 9.75c1.45.2 2.75 1.55 2.75 3.25M2.5 13c0-1.7 1.3-3.05 2.75-3.25" /></svg>',
        'doctors' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a3.75 3.75 0 1 0 0 7.5 3.75 3.75 0 0 0 0-7.5ZM4.5 21a7.5 7.5 0 0 1 15 0M18 4.5v4.5M20.25 6.75h-4.5" /></svg>',
        'calendar' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75v3M16.5 3.75v3M4.5 9.75h15M6 6h12a1.5 1.5 0 0 1 1.5 1.5V18A2.25 2.25 0 0 1 17.25 20.25H6.75A2.25 2.25 0 0 1 4.5 18V7.5A1.5 1.5 0 0 1 6 6Z" /></svg>',
        'consultations' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5h7.5M9 3h6a1.5 1.5 0 0 1 1.5 1.5v1.25h-9V4.5A1.5 1.5 0 0 1 9 3ZM6.75 5.75h10.5A2.25 2.25 0 0 1 19.5 8v10.25a2.25 2.25 0 0 1-2.25 2.25H6.75a2.25 2.25 0 0 1-2.25-2.25V8a2.25 2.25 0 0 1 2.25-2.25ZM8.25 11.25h7.5M8.25 15h4.5" /></svg>',
        'records' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 7.5A2.25 2.25 0 0 1 6 5.25h4.15c.6 0 1.18.24 1.6.66l1.09 1.09H18A2.25 2.25 0 0 1 20.25 9.25v7.5A2.25 2.25 0 0 1 18 19H6a2.25 2.25 0 0 1-2.25-2.25V7.5ZM12 10.75v4.5M9.75 13h4.5" /></svg>',
        'prescriptions' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75h6.75L18.75 8.25v12H7.5a2.25 2.25 0 0 1-2.25-2.25v-12A2.25 2.25 0 0 1 7.5 3.75ZM14.25 3.75v4.5h4.5M8.75 12.75h6.5M8.75 16h4.25" /></svg>',
        'payments' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 7.5A2.25 2.25 0 0 1 6 5.25h12A2.25 2.25 0 0 1 20.25 7.5v9A2.25 2.25 0 0 1 18 18.75H6A2.25 2.25 0 0 1 3.75 16.5v-9ZM3.75 9.75h16.5M7.5 15h3" /></svg>',
        'services' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h6.75v6.75H4.5V6.75ZM12.75 5.25h6.75M12.75 9h6.75M12.75 12.75h6.75M4.5 17.25h15" /></svg>',
        'reports' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5h15M7.5 16.5V10.5M12 16.5V6M16.5 16.5v-8.25M5.25 4.5h13.5" /></svg>',
        'users' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75 19.5 6v5.25c0 4.55-3.1 8.8-7.5 9.95-4.4-1.15-7.5-5.4-7.5-9.95V6L12 3.75ZM9 12l2 2 4-4" /></svg>',
        'settings' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.35 4.65 11 2.75h2l.65 1.9c.45.14.88.32 1.28.54l1.8-.88 1.42 1.42-.88 1.8c.22.4.4.83.54 1.28l1.9.65v2l-1.9.65c-.14.45-.32.88-.54 1.28l.88 1.8-1.42 1.42-1.8-.88c-.4.22-.83.4-1.28.54L13 21.25h-2l-.65-1.9a7.4 7.4 0 0 1-1.28-.54l-1.8.88-1.42-1.42.88-1.8a7.4 7.4 0 0 1-.54-1.28l-1.9-.65v-2l1.9-.65c.14-.45.32-.88.54-1.28l-.88-1.8 1.42-1.42 1.8.88c.4-.22.83-.4 1.28-.54ZM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z" /></svg>',
    ];

    $moduleLinks = [
        ['label' => 'Dashboard', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'dashboard', 'placeholder' => false],
        ['label' => 'Pacientes', 'href' => route('patients.index'), 'active' => request()->routeIs('patients.*'), 'icon' => 'patients', 'placeholder' => false],
        ['label' => 'Medicos', 'href' => route('doctors.index'), 'active' => request()->routeIs('doctors.*'), 'icon' => 'doctors', 'placeholder' => false],
        ['label' => 'Citas médicas', 'href' => route('appointments.index'), 'active' => request()->routeIs('appointments.*'), 'icon' => 'calendar', 'placeholder' => false],
        ['label' => 'Consultas', 'href' => route('consultations.index'), 'active' => request()->routeIs('consultations.*'), 'icon' => 'consultations', 'placeholder' => false],
        ['label' => 'Historial clínico', 'href' => route('medical-records.index'), 'active' => request()->routeIs('medical-records.*'), 'icon' => 'records', 'placeholder' => false],
        ['label' => 'Recetas médicas', 'href' => route('prescriptions.index'), 'active' => request()->routeIs('prescriptions.*'), 'icon' => 'prescriptions', 'placeholder' => false],
        ['label' => 'Pagos y Finanzas', 'href' => route('payments.index'), 'active' => request()->routeIs('payments.*'), 'icon' => 'payments', 'placeholder' => false],
        ['label' => 'Servicios médicos', 'href' => '#', 'active' => false, 'icon' => 'services', 'placeholder' => true],
        ['label' => 'Reportes', 'href' => '#', 'active' => false, 'icon' => 'reports', 'placeholder' => true],
        ['label' => 'Usuarios y Roles', 'href' => '#', 'active' => false, 'icon' => 'users', 'placeholder' => true],
        ['label' => 'Configuración', 'href' => '#', 'active' => false, 'icon' => 'settings', 'placeholder' => true],
    ];
@endphp

<div x-cloak x-show="sidebarOpen" class="relative z-50 lg:hidden" role="dialog" aria-modal="true">
    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 bg-[#0F172A]/45" @click="sidebarOpen = false"></div>

    <div class="fixed inset-0 flex">
        <div
            x-show="sidebarOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="relative flex w-72 max-w-[85vw] flex-1 flex-col bg-white shadow-xl"
        >
            <div class="flex h-16 items-center justify-between border-b border-[#E2E8F0] px-5">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-lg bg-[#2563EB] text-white shadow-sm shadow-blue-500/25">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                        </svg>
                    </span>
                    <span>
                        <span class="block text-base font-bold tracking-tight text-[#0F172A]">MediFlow</span>
                        <span class="block text-xs font-medium text-[#475569]">Gestión clínica</span>
                    </span>
                </a>

                <button type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700" @click="sidebarOpen = false">
                    <span class="sr-only">Cerrar menú</span>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav class="mediflow-scrollbar flex-1 space-y-1 overflow-y-auto px-3 py-4">
                @foreach ($moduleLinks as $item)
                    <a
                        href="{{ $item['href'] }}"
                        @if ($item['placeholder']) onclick="event.preventDefault()" aria-disabled="true" @endif
                        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition {{ $item['active'] ? 'bg-[#2563EB] text-white shadow-sm shadow-blue-500/20' : 'text-slate-600 hover:bg-slate-100 hover:text-[#0F172A]' }}"
                    >
                        <span class="grid h-9 w-9 place-items-center rounded-lg {{ $item['active'] ? 'bg-white/15 text-white' : 'text-slate-400 group-hover:bg-white group-hover:text-[#2563EB]' }}">
                            {!! $icons[$item['icon']] !!}
                        </span>
                        <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                        @if ($item['placeholder'])
                            <span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span>
                        @endif
                    </a>
                @endforeach
            </nav>
        </div>
    </div>
</div>

<aside class="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-[#E2E8F0] bg-white lg:flex lg:flex-col">
    <div class="flex h-16 shrink-0 items-center border-b border-[#E2E8F0] px-6">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-[#2563EB] text-white shadow-sm shadow-blue-500/25">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                </svg>
            </span>
            <span>
                <span class="block text-base font-bold tracking-tight text-[#0F172A]">MediFlow</span>
                <span class="block text-xs font-medium text-[#475569]">Gestión clínica</span>
            </span>
        </a>
    </div>

    <nav class="mediflow-scrollbar flex-1 space-y-1 overflow-y-auto px-3 py-4">
        @foreach ($moduleLinks as $item)
            <a
                href="{{ $item['href'] }}"
                @if ($item['placeholder']) onclick="event.preventDefault()" aria-disabled="true" @endif
                class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition {{ $item['active'] ? 'bg-[#2563EB] text-white shadow-sm shadow-blue-500/20' : 'text-slate-600 hover:bg-slate-100 hover:text-[#0F172A]' }}"
            >
                <span class="grid h-9 w-9 place-items-center rounded-lg {{ $item['active'] ? 'bg-white/15 text-white' : 'text-slate-400 group-hover:bg-white group-hover:text-[#2563EB]' }}">
                    {!! $icons[$item['icon']] !!}
                </span>
                <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                @if ($item['placeholder'])
                    <span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="border-t border-[#E2E8F0] p-4">
        <div class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-4">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-[#38BDF8]/15 text-[#2563EB]">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M5.25 21V6.75A2.25 2.25 0 0 1 7.5 4.5h9a2.25 2.25 0 0 1 2.25 2.25V21M9 9.75h6M9 13.5h6M10.5 21v-3h3v3" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-[#0F172A]">Consultorio principal</p>
                    <p class="text-xs font-medium text-[#10B981]">Activo</p>
                </div>
            </div>
        </div>
    </div>
</aside>

<header class="fixed inset-x-0 top-0 z-30 border-b border-[#E2E8F0] bg-white/95 backdrop-blur lg:left-72">
    <div class="flex h-16 items-center gap-4 px-4 sm:px-6 lg:px-8">
        <button type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 lg:hidden" @click="sidebarOpen = true">
            <span class="sr-only">Abrir menú</span>
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <div class="hidden min-w-0 flex-1 md:block">
            <label for="global-search" class="sr-only">Buscar</label>
            <div class="relative max-w-xl">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
                    </svg>
                </span>
                <input
                    id="global-search"
                    type="search"
                    placeholder="Buscar pacientes, citas, pagos..."
                    class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] py-2.5 pl-10 pr-4 text-sm text-[#0F172A] placeholder:text-slate-400 focus:border-[#2563EB] focus:ring-[#2563EB]"
                >
            </div>
        </div>

        <div class="flex flex-1 items-center justify-end gap-2 md:flex-none">
            <button type="button" class="relative grid h-10 w-10 place-items-center rounded-lg border border-[#E2E8F0] bg-white text-slate-500 transition hover:border-[#38BDF8] hover:text-[#2563EB]">
                <span class="sr-only">Notificaciones</span>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.85 18.75a3 3 0 0 1-5.7 0M18 10.5a6 6 0 1 0-12 0c0 6-2.25 6.75-2.25 6.75h16.5S18 16.5 18 10.5Z" />
                </svg>
                <span class="absolute right-2.5 top-2.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-[#EF4444]"></span>
            </button>

            <x-dropdown align="right" width="64" contentClasses="bg-white p-2">
                <x-slot name="trigger">
                    <button type="button" class="flex min-w-0 items-center gap-3 rounded-lg border border-transparent px-2 py-1.5 transition hover:border-[#E2E8F0] hover:bg-[#F8FAFC]">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-[#0F172A] text-sm font-bold text-white">
                            {{ $initials }}
                        </span>
                        <span class="hidden min-w-0 text-left sm:block">
                            <span class="block truncate text-sm font-semibold text-[#0F172A]">{{ $user?->name ?? 'Usuario' }}</span>
                            <span class="block truncate text-xs font-medium text-[#475569]">{{ $roleLabel }}</span>
                        </span>
                        <svg class="hidden h-4 w-4 shrink-0 text-slate-400 sm:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <div class="border-b border-[#E2E8F0] px-3 py-3">
                        <p class="truncate text-sm font-semibold text-[#0F172A]">{{ $user?->name ?? 'Usuario' }}</p>
                        <p class="truncate text-xs text-[#475569]">{{ $user?->email }}</p>
                        <span class="mt-2 inline-flex rounded-full bg-[#38BDF8]/15 px-2.5 py-1 text-xs font-semibold text-[#2563EB]">{{ $roleLabel }}</span>
                    </div>

                    <x-dropdown-link :href="route('profile.edit')" class="mt-2 rounded-md">
                        {{ __('Perfil') }}
                    </x-dropdown-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <x-dropdown-link
                            :href="route('logout')"
                            onclick="event.preventDefault(); this.closest('form').submit();"
                            class="rounded-md text-[#EF4444]"
                        >
                            {{ __('Cerrar sesión') }}
                        </x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    </div>
</header>
