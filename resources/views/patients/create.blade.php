<x-app-layout>
    <div class="space-y-6">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Pacientes</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Nuevo paciente</h1>
                <p class="mt-2 text-sm text-[#475569]">Registra la informacion principal del paciente.</p>
            </div>

            <a href="{{ route('patients.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569] transition hover:border-[#2563EB] hover:text-[#2563EB]">
                Volver
            </a>
        </section>

        <form method="POST" action="{{ route('patients.store') }}" class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            @csrf

            <div class="grid gap-5 p-5 md:grid-cols-2">
                <div>
                    <label for="first_name" class="mb-2 block text-sm font-semibold text-[#0F172A]">Nombres *</label>
                    <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    @error('first_name') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="last_name" class="mb-2 block text-sm font-semibold text-[#0F172A]">Apellidos *</label>
                    <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    @error('last_name') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="identification_number" class="mb-2 block text-sm font-semibold text-[#0F172A]">Identificacion</label>
                    <input id="identification_number" name="identification_number" type="text" value="{{ old('identification_number') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    @error('identification_number') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="birth_date" class="mb-2 block text-sm font-semibold text-[#0F172A]">Fecha de nacimiento</label>
                    <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    @error('birth_date') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="gender" class="mb-2 block text-sm font-semibold text-[#0F172A]">Genero</label>
                    <select id="gender" name="gender" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="">No especificado</option>
                        <option value="masculino" @selected(old('gender') === 'masculino')>Masculino</option>
                        <option value="femenino" @selected(old('gender') === 'femenino')>Femenino</option>
                        <option value="otro" @selected(old('gender') === 'otro')>Otro</option>
                    </select>
                    @error('gender') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="phone" class="mb-2 block text-sm font-semibold text-[#0F172A]">Telefono</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    @error('phone') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="mb-2 block text-sm font-semibold text-[#0F172A]">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    @error('email') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="blood_type" class="mb-2 block text-sm font-semibold text-[#0F172A]">Tipo de sangre</label>
                    <input id="blood_type" name="blood_type" type="text" value="{{ old('blood_type') }}" maxlength="10" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    @error('blood_type') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="address" class="mb-2 block text-sm font-semibold text-[#0F172A]">Direccion</label>
                    <input id="address" name="address" type="text" value="{{ old('address') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    @error('address') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="allergies" class="mb-2 block text-sm font-semibold text-[#0F172A]">Alergias</label>
                    <textarea id="allergies" name="allergies" rows="3" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('allergies') }}</textarea>
                    @error('allergies') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="emergency_contact_name" class="mb-2 block text-sm font-semibold text-[#0F172A]">Contacto de emergencia</label>
                    <input id="emergency_contact_name" name="emergency_contact_name" type="text" value="{{ old('emergency_contact_name') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    @error('emergency_contact_name') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="emergency_contact_phone" class="mb-2 block text-sm font-semibold text-[#0F172A]">Telefono de emergencia</label>
                    <input id="emergency_contact_phone" name="emergency_contact_phone" type="text" value="{{ old('emergency_contact_phone') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    @error('emergency_contact_phone') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado *</label>
                    <select id="status" name="status" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="active" @selected(old('status', 'active') === 'active')>Activo</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactivo</option>
                    </select>
                    @error('status') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-[#E2E8F0] px-5 py-4 sm:flex-row sm:justify-end">
                <a href="{{ route('patients.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569] transition hover:border-[#2563EB] hover:text-[#2563EB]">Cancelar</a>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">Guardar paciente</button>
            </div>
        </form>
    </div>
</x-app-layout>
