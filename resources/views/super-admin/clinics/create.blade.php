<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('super-admin.clinics.index') }}" class="grid h-10 w-10 place-items-center rounded-xl border border-[#E2E8F0] bg-white text-slate-500 transition hover:bg-[#F8FAFC] hover:text-[#0F172A]">
                <span class="sr-only">Volver</span>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Registrar Nueva Clínica</h1>
                <p class="mt-1 text-sm text-[#475569]">Da de alta una nueva organización y a su primer administrador.</p>
            </div>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('super-admin.clinics.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <div class="overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-sm">
            <div class="border-b border-[#E2E8F0] bg-[#F8FAFC] px-6 py-4">
                <h2 class="text-base font-bold text-[#0F172A]">1. Información de la Clínica</h2>
                <p class="text-sm text-[#475569]">Datos organizacionales, contacto y ubicación.</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="name" class="mb-2 block text-sm font-semibold text-[#0F172A]">Nombre Comercial <span class="text-[#EF4444]">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('name') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="legal_name" class="mb-2 block text-sm font-semibold text-[#0F172A]">Razón Social</label>
                        <input type="text" name="legal_name" id="legal_name" value="{{ old('legal_name') }}" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('legal_name') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="ruc" class="mb-2 block text-sm font-semibold text-[#0F172A]">RUC / Identificación Fiscal</label>
                        <input type="text" name="ruc" id="ruc" value="{{ old('ruc') }}" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('ruc') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="legal_representative" class="mb-2 block text-sm font-semibold text-[#0F172A]">Representante Legal</label>
                        <input type="text" name="legal_representative" id="legal_representative" value="{{ old('legal_representative') }}" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('legal_representative') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="clinic_type" class="mb-2 block text-sm font-semibold text-[#0F172A]">Tipo de Establecimiento</label>
                        <input type="text" name="clinic_type" id="clinic_type" value="{{ old('clinic_type') }}" placeholder="Ej: Consultorio, Policlínico..." class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('clinic_type') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2 mt-4 border-t border-[#E2E8F0] pt-6">
                        <h3 class="text-sm font-bold text-[#0F172A] mb-4">Contacto y Ubicación</h3>
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-sm font-semibold text-[#0F172A]">Correo de la clínica</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('email') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="website" class="mb-2 block text-sm font-semibold text-[#0F172A]">Sitio Web</label>
                        <input type="text" name="website" id="website" value="{{ old('website') }}" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('website') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="phone" class="mb-2 block text-sm font-semibold text-[#0F172A]">Teléfono Principal</label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone') }}" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('phone') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="secondary_phone" class="mb-2 block text-sm font-semibold text-[#0F172A]">Teléfono Secundario / WhatsApp</label>
                        <input type="text" name="secondary_phone" id="secondary_phone" value="{{ old('secondary_phone') }}" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('secondary_phone') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="country" class="mb-2 block text-sm font-semibold text-[#0F172A]">País</label>
                        <input type="text" name="country" id="country" value="{{ old('country') }}" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('country') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="state" class="mb-2 block text-sm font-semibold text-[#0F172A]">Provincia / Estado</label>
                        <input type="text" name="state" id="state" value="{{ old('state') }}" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('state') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="city" class="mb-2 block text-sm font-semibold text-[#0F172A]">Ciudad</label>
                        <input type="text" name="city" id="city" value="{{ old('city') }}" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('city') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="address" class="mb-2 block text-sm font-semibold text-[#0F172A]">Dirección Exacta</label>
                        <input type="text" name="address" id="address" value="{{ old('address') }}" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('address') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>
                    
                    <div class="sm:col-span-2 mt-4 border-t border-[#E2E8F0] pt-6">
                        <h3 class="text-sm font-bold text-[#0F172A] mb-4">Personalización y Suscripción SaaS</h3>
                    </div>

                    <div>
                        <label for="logo" class="mb-2 block text-sm font-semibold text-[#0F172A]">Logotipo de la Clínica</label>
                        <input type="file" name="logo" id="logo" accept="image/*" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <p class="mt-1 text-xs text-[#475569]">Formato JPG, PNG o SVG. Max 2MB.</p>
                        @error('logo') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="subscription_plan" class="mb-2 block text-sm font-semibold text-[#0F172A]">Plan Suscrito</label>
                        <select name="subscription_plan" id="subscription_plan" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm font-semibold text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                            <option value="basic" @selected(old('subscription_plan') === 'basic')>Básico</option>
                            <option value="pro" @selected(old('subscription_plan') === 'pro')>Profesional</option>
                            <option value="enterprise" @selected(old('subscription_plan') === 'enterprise')>Enterprise</option>
                        </select>
                        @error('subscription_plan') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="subscription_end_date" class="mb-2 block text-sm font-semibold text-[#0F172A]">Fecha de Vencimiento de Suscripción</label>
                        <input type="date" name="subscription_end_date" id="subscription_end_date" value="{{ old('subscription_end_date') }}" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('subscription_end_date') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado de Operación <span class="text-[#EF4444]">*</span></label>
                        <select name="status" id="status" required class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm font-semibold text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                            <option value="active" @selected(old('status', 'active') === 'active')>Activa (Puede usar el sistema)</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>Inactiva (Bloqueada)</option>
                        </select>
                        @error('status') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="internal_notes" class="mb-2 block text-sm font-semibold text-[#0F172A]">Notas Internas (Solo visibles para ti)</label>
                        <textarea name="internal_notes" id="internal_notes" rows="3" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('internal_notes') }}</textarea>
                        @error('internal_notes') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-sm">
            <div class="border-b border-[#E2E8F0] bg-[#F8FAFC] px-6 py-4">
                <h2 class="text-base font-bold text-[#0F172A]">2. Administrador Raíz</h2>
                <p class="text-sm text-[#475569]">Se creará este usuario automáticamente con el rol de Administrador de la clínica.</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="admin_name" class="mb-2 block text-sm font-semibold text-[#0F172A]">Nombre completo <span class="text-[#EF4444]">*</span></label>
                        <input type="text" name="admin_name" id="admin_name" value="{{ old('admin_name') }}" required class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('admin_name') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="admin_email" class="mb-2 block text-sm font-semibold text-[#0F172A]">Correo electrónico (Usuario de ingreso) <span class="text-[#EF4444]">*</span></label>
                        <input type="email" name="admin_email" id="admin_email" value="{{ old('admin_email') }}" required class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]" placeholder="admin@clinica.com">
                        @error('admin_email') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="admin_password" class="mb-2 block text-sm font-semibold text-[#0F172A]">Contraseña <span class="text-[#EF4444]">*</span></label>
                        <input type="password" name="admin_password" id="admin_password" required minlength="8" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('admin_password') <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="admin_password_confirmation" class="mb-2 block text-sm font-semibold text-[#0F172A]">Confirmar contraseña <span class="text-[#EF4444]">*</span></label>
                        <input type="password" name="admin_password_confirmation" id="admin_password_confirmation" required minlength="8" class="block w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <a href="{{ route('super-admin.clinics.index') }}" class="text-sm font-bold text-[#475569] hover:text-[#0F172A]">Cancelar</a>
            <button type="submit" class="rounded-xl bg-[#2563EB] px-6 py-2.5 text-sm font-bold text-white transition hover:bg-[#1D4ED8] focus:outline-none focus:ring-2 focus:ring-[#2563EB] focus:ring-offset-2">
                Crear Clínica y Administrador
            </button>
        </div>
    </form>
</x-app-layout>
