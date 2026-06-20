<header class="fixed inset-x-0 top-0 z-50 border-b border-white/70 bg-white/90 backdrop-blur-xl" x-data="{ mobileOpen: false }">
    <nav class="mx-auto flex h-18 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8" aria-label="Navegación principal">
        <a href="#inicio" class="flex items-center gap-3" aria-label="MediFlow, inicio">
            <img src="{{ asset('brand/mediflow-logo-primary-cropped.png') }}" alt="MediFlow" class="h-10 w-auto md:h-12 lg:h-14">
        </a>

        <div class="hidden items-center gap-7 lg:flex">
            @foreach ([
                ['href' => '#inicio', 'label' => 'Inicio'],
                ['href' => '#beneficios', 'label' => 'Beneficios'],
                ['href' => '#modulos', 'label' => 'Módulos'],
                ['href' => '#seguridad', 'label' => 'Seguridad'],
                ['href' => '#planes', 'label' => 'Planes'],
                ['href' => '#contacto', 'label' => 'Contacto'],
            ] as $link)
                <a href="{{ $link['href'] }}" class="public-action text-sm font-semibold text-[#475569] hover:text-[#2563EB]">{{ $link['label'] }}</a>
            @endforeach
        </div>

        <div class="hidden items-center gap-3 lg:flex">
            @auth
                <a href="{{ route('dashboard') }}" class="public-action rounded-xl bg-[#2563EB] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-500/20 hover:bg-blue-700">Ir al dashboard</a>
            @else
                <a href="{{ route('login') }}" class="public-action rounded-xl border border-[#E2E8F0] bg-white px-5 py-2.5 text-sm font-bold text-[#0F172A] hover:border-[#2563EB] hover:text-[#2563EB]">Iniciar sesión</a>
                <a href="#contacto" class="public-action rounded-xl bg-[#2563EB] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-500/20 hover:bg-blue-700">Solicitar demo</a>
            @endauth
        </div>

        <button type="button" class="grid h-10 w-10 place-items-center rounded-lg border border-[#E2E8F0] text-[#475569] lg:hidden" @click="mobileOpen = ! mobileOpen" :aria-expanded="mobileOpen" aria-controls="mobile-navigation">
            <span class="sr-only">Abrir menú</span>
            <svg x-show="!mobileOpen" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
            <svg x-cloak x-show="mobileOpen" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
        </button>
    </nav>

    <div id="mobile-navigation" x-cloak x-show="mobileOpen" x-transition class="border-t border-[#E2E8F0] bg-white px-4 py-5 lg:hidden">
        <div class="mx-auto grid max-w-7xl gap-1">
            @foreach ([
                ['href' => '#inicio', 'label' => 'Inicio'],
                ['href' => '#beneficios', 'label' => 'Beneficios'],
                ['href' => '#modulos', 'label' => 'Módulos'],
                ['href' => '#seguridad', 'label' => 'Seguridad'],
                ['href' => '#planes', 'label' => 'Planes'],
                ['href' => '#contacto', 'label' => 'Contacto'],
            ] as $link)
                <a href="{{ $link['href'] }}" @click="mobileOpen = false" class="public-action rounded-lg px-3 py-2.5 text-sm font-semibold text-[#475569] hover:bg-[#F8FAFC] hover:text-[#2563EB]">{{ $link['label'] }}</a>
            @endforeach
            <div class="mt-3 grid gap-2 border-t border-[#E2E8F0] pt-4 sm:grid-cols-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-xl bg-[#2563EB] px-5 py-3 text-center text-sm font-bold text-white">Ir al dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-xl border border-[#E2E8F0] px-5 py-3 text-center text-sm font-bold text-[#0F172A]">Iniciar sesión</a>
                    <a href="#contacto" @click="mobileOpen = false" class="rounded-xl bg-[#2563EB] px-5 py-3 text-center text-sm font-bold text-white">Solicitar demo</a>
                @endauth
            </div>
        </div>
    </div>
</header>
