<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Panel Súper Admin: Clínicas</h1>
                <p class="mt-2 text-sm leading-6 text-[#475569]">Gestiona las organizaciones registradas y sus suscripciones en la plataforma SaaS.</p>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <a href="{{ route('super-admin.clinics.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#2563EB] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#1D4ED8] focus:outline-none focus:ring-2 focus:ring-[#2563EB] focus:ring-offset-2">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Nueva Clínica
                </a>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 flex items-start gap-3 rounded-xl bg-[#10B981]/10 p-4 text-[#047857]">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <p class="text-sm font-semibold">{{ session('status') }}</p>
        </div>
    @endif

    <div class="mb-6">
        <form method="GET" action="{{ route('super-admin.clinics.index') }}" class="flex max-w-lg items-center gap-2">
            <div class="relative flex-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
                    </svg>
                </div>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Buscar clínica por nombre, RUC o email..." class="block w-full rounded-xl border-[#E2E8F0] bg-white py-2 pl-10 pr-3 text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            </div>
            <button type="submit" class="inline-flex justify-center rounded-xl bg-white px-4 py-2 text-sm font-bold text-[#0F172A] shadow-sm ring-1 ring-inset ring-[#E2E8F0] hover:bg-slate-50">
                Buscar
            </button>
        </form>
    </div>

    <div class="overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#E2E8F0]">
                <thead class="bg-[#F8FAFC]">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569] sm:pl-6">Clínica</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">RUC</th>
                        <th scope="col" class="px-3 py-3.5 text-center text-xs font-bold uppercase tracking-wide text-[#475569]">Usuarios</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Estado</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Registro</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E8F0] bg-white">
                    @forelse ($clinics as $clinic)
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6">
                                <div class="font-bold text-[#0F172A]">{{ $clinic->name }}</div>
                                <div class="text-sm text-[#475569]">{{ $clinic->email ?? 'Sin email' }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-[#475569]">
                                {{ $clinic->ruc ?? 'N/A' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-center text-sm text-[#475569]">
                                <span class="inline-flex items-center justify-center rounded-full bg-slate-100 px-2.5 py-0.5 text-sm font-semibold text-[#0F172A]">
                                    {{ $clinic->users_count }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide {{ $clinic->status === 'active' ? 'bg-[#10B981]/10 text-[#047857]' : 'bg-[#EF4444]/10 text-[#EF4444]' }}">
                                    {{ $clinic->status === 'active' ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-[#475569]">
                                {{ $clinic->created_at->format('d/m/Y') }}
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <a href="{{ route('super-admin.clinics.edit', $clinic) }}" class="inline-flex items-center gap-1 text-[#2563EB] hover:text-[#1D4ED8]">
                                    Editar
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center">
                                <div class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-400">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </div>
                                <h3 class="mt-4 text-sm font-semibold text-[#0F172A]">No hay clínicas</h3>
                                <p class="mt-1 text-sm text-[#475569]">No se encontraron clínicas que coincidan con tu búsqueda.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($clinics->hasPages())
            <div class="border-t border-[#E2E8F0] px-4 py-3 sm:px-6">
                {{ $clinics->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
