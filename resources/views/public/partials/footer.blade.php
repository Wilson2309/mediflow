<footer class="bg-[#0F172A] text-white">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">
        <div class="lg:col-span-2">
            <a href="#inicio" class="inline-flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#2563EB]">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                </span>
                <span class="text-xl font-extrabold">MediFlow</span>
            </a>
            <p class="mt-5 max-w-md text-sm leading-7 text-slate-300">Plataforma de gestión clínica, administrativa y financiera para consultorios privados, centros médicos pequeños y profesionales independientes.</p>
        </div>
        <div>
            <p class="text-sm font-bold uppercase tracking-wider text-[#38BDF8]">Enlaces rápidos</p>
            <div class="mt-4 grid gap-3 text-sm text-slate-300">
                <a href="#beneficios" class="hover:text-white">Beneficios</a>
                <a href="#seguridad" class="hover:text-white">Seguridad</a>
                <a href="#planes" class="hover:text-white">Planes</a>
                <a href="#contacto" class="hover:text-white">Solicitar demo</a>
            </div>
        </div>
        <div>
            <p class="text-sm font-bold uppercase tracking-wider text-[#38BDF8]">Módulos</p>
            <div class="mt-4 grid gap-3 text-sm text-slate-300">
                <a href="#modulos" class="hover:text-white">Gestión clínica</a>
                <a href="#reportes" class="hover:text-white">Reportes</a>
                <a href="#roles" class="hover:text-white">Usuarios y roles</a>
                @guest<a href="{{ route('login') }}" class="hover:text-white">Iniciar sesión</a>@endguest
            </div>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <p>&copy; {{ now()->year }} MediFlow. Todos los derechos reservados.</p>
            <p>Gestión médica clara, segura y organizada.</p>
        </div>
    </div>
</footer>
