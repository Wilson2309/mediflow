@php
    $problems = [
        ['title' => 'Información dispersa', 'text' => 'Datos repartidos entre cuadernos, hojas de cálculo y conversaciones que dificultan encontrar lo importante.'],
        ['title' => 'Agenda difícil de controlar', 'text' => 'Citas duplicadas, cambios sin seguimiento y poca visibilidad de la disponibilidad médica.'],
        ['title' => 'Historial clínico desordenado', 'text' => 'Antecedentes, consultas y diagnósticos sin una secuencia clara para tomar mejores decisiones.'],
        ['title' => 'Recetas en papel', 'text' => 'Indicaciones difíciles de consultar y desconectadas del historial del paciente.'],
        ['title' => 'Pagos sin seguimiento', 'text' => 'Cobros pendientes e ingresos que requieren revisión manual para conocer el estado real.'],
        ['title' => 'Decisiones sin reportes', 'text' => 'Sin indicadores confiables sobre citas, actividad clínica, pacientes, servicios e ingresos.'],
    ];

    $benefits = [
        ['title' => 'Organización centralizada', 'text' => 'La información clínica y administrativa vive en un solo lugar, conectada y fácil de consultar.', 'color' => 'blue'],
        ['title' => 'Atención más rápida', 'text' => 'El equipo encuentra pacientes, citas, antecedentes y recetas sin perder tiempo entre herramientas.', 'color' => 'sky'],
        ['title' => 'Control financiero', 'text' => 'Registra pagos, estados y montos para mantener una visión clara de los ingresos del consultorio.', 'color' => 'green'],
        ['title' => 'Reportes en tiempo real', 'text' => 'Convierte los datos registrados en indicadores útiles para supervisar la operación.', 'color' => 'blue'],
        ['title' => 'Seguridad por roles', 'text' => 'Cada perfil accede únicamente a las funciones necesarias para realizar su trabajo.', 'color' => 'yellow'],
        ['title' => 'Diseñado para crecer', 'text' => 'Funciona para profesionales independientes, consultorios privados y centros médicos pequeños.', 'color' => 'sky'],
    ];

    $modules = [
        ['name' => 'Pacientes', 'text' => 'Datos personales, contacto, estado y acceso a su información relacionada.', 'icon' => 'users'],
        ['name' => 'Médicos', 'text' => 'Perfiles profesionales, especialidades, licencias, tarifas y disponibilidad operativa.', 'icon' => 'medical'],
        ['name' => 'Servicios', 'text' => 'Catálogo de atenciones con duración, precio y estado.', 'icon' => 'services'],
        ['name' => 'Citas', 'text' => 'Agenda organizada por paciente, médico, servicio, fecha, hora y estado.', 'icon' => 'calendar'],
        ['name' => 'Consultas', 'text' => 'Registro clínico de síntomas, diagnóstico, tratamiento y observaciones.', 'icon' => 'clipboard'],
        ['name' => 'Historial clínico', 'text' => 'Antecedentes, alergias y enfermedades crónicas disponibles de forma ordenada.', 'icon' => 'folder'],
        ['name' => 'Recetas', 'text' => 'Medicamentos, dosis, frecuencia, duración e indicaciones vinculadas al paciente.', 'icon' => 'prescription'],
        ['name' => 'Pagos', 'text' => 'Control de montos, métodos de pago, estados y cartera pendiente.', 'icon' => 'payment'],
        ['name' => 'Reportes', 'text' => 'Indicadores clínicos, administrativos y financieros basados en datos reales.', 'icon' => 'chart'],
        ['name' => 'Usuarios y roles', 'text' => 'Administración del equipo con permisos diferenciados por responsabilidad.', 'icon' => 'shield'],
        ['name' => 'Configuración', 'text' => 'Información principal del consultorio centralizada y editable.', 'icon' => 'settings'],
    ];

    $workflow = ['Registrar paciente', 'Agendar cita', 'Atender consulta', 'Crear receta', 'Registrar pago', 'Revisar reportes'];

    $roles = [
        ['name' => 'Administrador', 'text' => 'Acceso completo a la operación, usuarios, configuración y reportes.', 'badge' => 'Control total'],
        ['name' => 'Médico', 'text' => 'Atención clínica, pacientes, consultas, recetas e historial médico.', 'badge' => 'Área clínica'],
        ['name' => 'Recepcionista', 'text' => 'Registro de pacientes, coordinación de citas y consulta de agenda.', 'badge' => 'Operación diaria'],
        ['name' => 'Caja / finanzas', 'text' => 'Pagos, lectura operativa necesaria y reportes financieros.', 'badge' => 'Control financiero'],
    ];

    $reports = ['Reporte financiero', 'Reporte clínico', 'Reporte de citas', 'Reporte de pacientes', 'Reporte de médicos', 'Reporte de servicios'];

    $plans = [
        ['name' => 'Básico', 'description' => 'Para profesionales independientes que necesitan ordenar su operación.', 'features' => ['Pacientes y citas', 'Consultas e historial', 'Recetas médicas', 'Soporte inicial'], 'featured' => false],
        ['name' => 'Profesional', 'description' => 'Para consultorios que buscan control clínico y financiero completo.', 'features' => ['Todos los módulos clínicos', 'Pagos y finanzas', 'Reportes y analítica', 'Usuarios con roles'], 'featured' => true],
        ['name' => 'Centro médico', 'description' => 'Para equipos con varios médicos y responsabilidades diferenciadas.', 'features' => ['Múltiples médicos', 'Roles operativos', 'Configuración centralizada', 'Acompañamiento de implementación'], 'featured' => false],
    ];

    $faqs = [
        ['question' => '¿MediFlow sirve para consultorios pequeños?', 'answer' => 'Sí. Está diseñado para organizar la operación de profesionales independientes, consultorios privados y centros médicos pequeños sin añadir complejidad innecesaria.'],
        ['question' => '¿Puedo manejar varios médicos?', 'answer' => 'Sí. Puedes registrar múltiples perfiles médicos, especialidades, tarifas y citas asociadas dentro de la clínica.'],
        ['question' => '¿Los usuarios tienen permisos diferentes?', 'answer' => 'Sí. MediFlow utiliza roles para separar el acceso de administradores, médicos, recepción y caja o finanzas.'],
        ['question' => '¿Se pueden generar reportes?', 'answer' => 'Sí. El sistema incluye reportes financieros, clínicos, de citas, pacientes, médicos y servicios.'],
        ['question' => '¿Se podrán enviar recetas por correo en el futuro?', 'answer' => 'Está contemplado como una mejora futura. Actualmente las recetas se gestionan dentro del sistema y la integración de correo se añadirá en una fase posterior.'],
    ];
@endphp

<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="MediFlow centraliza la gestión clínica, administrativa y financiera de consultorios y centros médicos pequeños.">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MediFlow | Gestión médica en una sola plataforma</title>
    <script>document.documentElement.classList.add('js');</script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white font-sans text-[#0F172A] antialiased selection:bg-[#2563EB] selection:text-white">
    @include('public.partials.navbar')

    <main>
        <section id="inicio" class="relative overflow-hidden bg-[#F8FAFC] pt-28 lg:pt-36">
            <div class="absolute inset-0" aria-hidden="true">
                <div class="absolute -left-24 top-24 h-80 w-80 rounded-full bg-[#38BDF8]/15 blur-3xl"></div>
                <div class="absolute -right-24 top-10 h-96 w-96 rounded-full bg-[#2563EB]/10 blur-3xl"></div>
                <div class="absolute inset-0 bg-[linear-gradient(to_right,#E2E8F050_1px,transparent_1px),linear-gradient(to_bottom,#E2E8F050_1px,transparent_1px)] bg-[size:48px_48px] [mask-image:linear-gradient(to_bottom,black,transparent_80%)]"></div>
            </div>

            <div data-reveal class="relative mx-auto grid max-w-7xl items-center gap-14 px-4 pb-20 sm:px-6 lg:grid-cols-[1.05fr_.95fr] lg:px-8 lg:pb-28">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-[#2563EB]/15 bg-white px-3 py-1.5 text-xs font-bold text-[#2563EB] shadow-sm">
                        <span class="h-2 w-2 rounded-full bg-[#10B981]"></span>
                        Gestión clínica, administrativa y financiera
                    </div>
                    <h1 class="mt-6 max-w-3xl text-4xl font-extrabold tracking-tight text-[#0F172A] sm:text-5xl lg:text-6xl lg:leading-[1.08]">Gestiona tu consultorio médico desde una sola plataforma</h1>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-[#475569]">MediFlow centraliza pacientes, citas, consultas, recetas, pagos, reportes y usuarios en un sistema moderno, seguro y fácil de usar.</p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="#contacto" class="public-action inline-flex items-center justify-center gap-2 rounded-xl bg-[#2563EB] px-6 py-3.5 text-sm font-bold text-white shadow-xl shadow-blue-500/20 hover:bg-blue-700">
                            Solicitar demo
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                        </a>
                        @auth
                            <a href="{{ route('dashboard') }}" class="public-action inline-flex items-center justify-center rounded-xl border border-[#E2E8F0] bg-white px-6 py-3.5 text-sm font-bold text-[#0F172A] shadow-sm hover:border-[#2563EB] hover:text-[#2563EB]">Ir al dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="public-action inline-flex items-center justify-center rounded-xl border border-[#E2E8F0] bg-white px-6 py-3.5 text-sm font-bold text-[#0F172A] shadow-sm hover:border-[#2563EB] hover:text-[#2563EB]">Iniciar sesión</a>
                        @endauth
                    </div>
                    <div class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-semibold text-[#475569]">
                        @foreach (['Datos centralizados', 'Permisos por rol', 'Reportes reales'] as $item)
                            <span class="inline-flex items-center gap-2"><svg class="h-4 w-4 text-[#10B981]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>{{ $item }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="relative">
                    <div class="absolute -inset-5 rounded-[2rem] bg-gradient-to-br from-[#2563EB]/20 via-[#38BDF8]/10 to-transparent blur-2xl"></div>
                    <div class="relative overflow-hidden rounded-2xl border border-white/80 bg-white shadow-2xl shadow-slate-900/15">
                        <div class="flex items-center justify-between border-b border-[#E2E8F0] px-5 py-4">
                            <div class="flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-lg bg-[#2563EB] text-white"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg></span><div><p class="text-sm font-bold">Panel MediFlow</p><p class="text-xs text-[#475569]">Resumen operativo</p></div></div>
                            <span class="rounded-full bg-[#10B981]/10 px-2.5 py-1 text-xs font-bold text-[#047857]">Datos en línea</span>
                        </div>
                        <div class="bg-[#F8FAFC] p-5">
                            <div class="grid grid-cols-2 gap-3">
                                @foreach ([
                                    ['label' => 'Pacientes activos', 'value' => '1,248', 'tone' => 'bg-[#2563EB]', 'trend' => '+12%'],
                                    ['label' => 'Citas de hoy', 'value' => '18', 'tone' => 'bg-[#38BDF8]', 'trend' => 'Agenda'],
                                    ['label' => 'Ingresos del mes', 'value' => '$8,420', 'tone' => 'bg-[#10B981]', 'trend' => '+8.4%'],
                                    ['label' => 'Reportes', 'value' => '6', 'tone' => 'bg-[#0F172A]', 'trend' => 'Actualizados'],
                                ] as $metric)
                                    <article class="rounded-xl border border-[#E2E8F0] bg-white p-4 shadow-sm">
                                        <div class="flex items-center justify-between"><span class="h-2.5 w-2.5 rounded-full {{ $metric['tone'] }}"></span><span class="text-[10px] font-bold text-[#10B981]">{{ $metric['trend'] }}</span></div>
                                        <p class="mt-4 text-xl font-extrabold tracking-tight">{{ $metric['value'] }}</p>
                                        <p class="mt-1 text-xs font-medium text-[#475569]">{{ $metric['label'] }}</p>
                                    </article>
                                @endforeach
                            </div>
                            <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-white p-4 shadow-sm">
                                <div class="flex items-center justify-between"><div><p class="text-sm font-bold">Actividad semanal</p><p class="mt-1 text-xs text-[#475569]">Consultas registradas</p></div><span class="rounded-lg bg-[#2563EB]/10 px-2 py-1 text-xs font-bold text-[#2563EB]">Esta semana</span></div>
                                <div class="mt-5 flex h-28 items-end gap-2">
                                    @foreach ([38, 62, 48, 78, 68, 92, 56] as $height)
                                        <div class="flex-1 rounded-t-md bg-gradient-to-t from-[#2563EB] to-[#38BDF8]" style="height: {{ $height }}%"></div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-y border-[#E2E8F0] bg-white py-7">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 text-center sm:px-6 md:flex-row md:text-left lg:px-8">
                <p class="text-sm font-bold text-[#0F172A]">Una operación más clara para todo tu equipo</p>
                <div class="flex flex-wrap justify-center gap-x-8 gap-y-3 text-xs font-bold uppercase tracking-wider text-[#64748B] md:justify-end"><span>Consultorios privados</span><span>Centros médicos</span><span>Profesionales independientes</span></div>
            </div>
        </section>

        <section class="bg-white py-20 lg:py-28">
            <div data-reveal class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-12 lg:grid-cols-[.8fr_1.2fr] lg:items-start">
                    <div class="lg:sticky lg:top-28">
                        <p class="text-sm font-bold uppercase tracking-wider text-[#2563EB]">El problema</p>
                        <h2 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Tu consultorio no debería depender de información fragmentada</h2>
                        <p class="mt-5 text-base leading-7 text-[#475569]">Cuando cada proceso vive en una herramienta diferente, el equipo pierde tiempo, aumenta los errores y disminuye la visibilidad del negocio.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($problems as $index => $problem)
                            <article class="public-card group rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-5 hover:border-[#2563EB]/30 hover:bg-white hover:shadow-lg">
                                <span class="grid h-9 w-9 place-items-center rounded-lg bg-[#EF4444]/10 text-sm font-extrabold text-[#EF4444]">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                <h3 class="mt-4 font-bold">{{ $problem['title'] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-[#475569]">{{ $problem['text'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section id="beneficios" class="scroll-mt-20 bg-[#F8FAFC] py-20 lg:py-28">
            <div data-reveal class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <p class="text-sm font-bold uppercase tracking-wider text-[#2563EB]">Beneficios</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Más control para tu equipo, más continuidad para tus pacientes</h2>
                    <p class="mt-5 text-base leading-7 text-[#475569]">MediFlow conecta la atención clínica con la operación administrativa para que cada área trabaje con información confiable.</p>
                </div>
                <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($benefits as $benefit)
                        @php($tone = match($benefit['color']) { 'green' => 'bg-[#10B981]/10 text-[#047857]', 'yellow' => 'bg-[#F59E0B]/10 text-[#B45309]', 'sky' => 'bg-[#38BDF8]/15 text-[#0369A1]', default => 'bg-[#2563EB]/10 text-[#2563EB]' })
                        <article class="public-card rounded-2xl border border-[#E2E8F0] bg-white p-6 shadow-sm hover:shadow-xl">
                            <span class="grid h-11 w-11 place-items-center rounded-xl {{ $tone }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg></span>
                            <h3 class="mt-5 text-lg font-bold">{{ $benefit['title'] }}</h3>
                            <p class="mt-2 text-sm leading-6 text-[#475569]">{{ $benefit['text'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="modulos" class="scroll-mt-20 bg-white py-20 lg:py-28">
            <div data-reveal class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl"><p class="text-sm font-bold uppercase tracking-wider text-[#2563EB]">Módulos conectados</p><h2 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Todo lo necesario para operar desde un mismo sistema</h2></div>
                    <a href="#contacto" class="public-action inline-flex items-center gap-2 text-sm font-bold text-[#2563EB]">Ver MediFlow en acción <span aria-hidden="true">→</span></a>
                </div>
                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($modules as $module)
                        <article class="public-card group flex gap-4 rounded-2xl border border-[#E2E8F0] p-5 hover:border-[#2563EB]/40 hover:shadow-lg">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#2563EB]/10 text-[#2563EB] transition group-hover:bg-[#2563EB] group-hover:text-white">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    @switch($module['icon'])
                                        @case('calendar') <path stroke-linecap="round" stroke-linejoin="round" d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1Z" /> @break
                                        @case('chart') <path stroke-linecap="round" stroke-linejoin="round" d="M4 19h16M7 16v-5m5 5V6m5 10V9" /> @break
                                        @case('shield') <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 19 6v5c0 4.5-2.8 8-7 10-4.2-2-7-5.5-7-10V6l7-3Zm-3 9 2 2 4-4" /> @break
                                        @case('payment') <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18v11H3V7Zm0 4h18M7 15h3" /> @break
                                        @case('settings') <path stroke-linecap="round" stroke-linejoin="round" d="M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm0-6v2m0 14v2M3 12h2m14 0h2M5.6 5.6 7 7m10 10 1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4" /> @break
                                        @case('medical') <path stroke-linecap="round" stroke-linejoin="round" d="M12 5a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM5 21a7 7 0 0 1 14 0m0-16v4m2-2h-4" /> @break
                                        @case('services') <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h6v6H4V5Zm10 1h6m-6 4h6M4 15h16M4 19h10" /> @break
                                        @case('folder') <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h7l2 2h9v10H3V7Zm9 5v4m-2-2h4" /> @break
                                        @case('prescription') <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l4 4v14H7V3Zm7 0v5h4M10 12h5m-5 4h3" /> @break
                                        @case('clipboard') <path stroke-linecap="round" stroke-linejoin="round" d="M9 4h6l1 2h3v15H5V6h3l1-2Zm0 7h6m-6 4h6" /> @break
                                        @default <path stroke-linecap="round" stroke-linejoin="round" d="M12 4a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM5 21a7 7 0 0 1 14 0" />
                                    @endswitch
                                </svg>
                            </span>
                            <div><h3 class="font-bold">{{ $module['name'] }}</h3><p class="mt-1.5 text-sm leading-6 text-[#475569]">{{ $module['text'] }}</p></div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="overflow-hidden bg-[#0F172A] py-20 text-white lg:py-28">
            <div data-reveal class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-12 lg:grid-cols-[.8fr_1.2fr] lg:items-center">
                    <div><p class="text-sm font-bold uppercase tracking-wider text-[#38BDF8]">Flujo de trabajo</p><h2 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">De la recepción al reporte, sin perder continuidad</h2><p class="mt-5 text-base leading-7 text-slate-300">Cada paso alimenta al siguiente. El equipo trabaja sobre la misma información y la administración obtiene indicadores reales.</p></div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($workflow as $index => $step)
                            <div class="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#2563EB] text-sm font-extrabold">{{ $index + 1 }}</span>
                                <div><p class="text-xs font-bold uppercase tracking-wider text-[#38BDF8]">Paso {{ $index + 1 }}</p><p class="mt-1 font-bold">{{ $step }}</p></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section id="roles" class="scroll-mt-20 bg-[#F8FAFC] py-20 lg:py-28">
            <div data-reveal class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-3xl text-center"><p class="text-sm font-bold uppercase tracking-wider text-[#2563EB]">Trabajo en equipo</p><h2 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Cada usuario ve las herramientas que necesita</h2><p class="mt-5 text-base leading-7 text-[#475569]">Los permisos por rol ayudan a separar responsabilidades sin desconectar la operación.</p></div>
                <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                    @foreach ($roles as $role)
                        <article class="public-card rounded-2xl border border-[#E2E8F0] bg-white p-6 shadow-sm"><span class="rounded-full bg-[#2563EB]/10 px-3 py-1 text-xs font-bold text-[#2563EB]">{{ $role['badge'] }}</span><h3 class="mt-5 text-lg font-bold">{{ $role['name'] }}</h3><p class="mt-2 text-sm leading-6 text-[#475569]">{{ $role['text'] }}</p></article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="seguridad" class="scroll-mt-20 bg-white py-20 lg:py-28">
            <div data-reveal class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:items-center lg:px-8">
                <div class="relative rounded-3xl bg-gradient-to-br from-[#2563EB] to-[#0F172A] p-7 text-white shadow-2xl shadow-blue-900/20 sm:p-10">
                    <div class="absolute right-6 top-6 h-24 w-24 rounded-full border border-white/10"></div>
                    <span class="grid h-14 w-14 place-items-center rounded-2xl bg-white/15"><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 19 6v5c0 4.5-2.8 8-7 10-4.2-2-7-5.5-7-10V6l7-3Zm-3 9 2 2 4-4" /></svg></span>
                    <h3 class="mt-8 text-2xl font-extrabold">Seguridad aplicada a la operación diaria</h3>
                    <p class="mt-3 max-w-lg text-sm leading-7 text-blue-100">MediFlow combina autenticación, permisos y validaciones para proteger el acceso a los módulos y mantener separados los datos de cada clínica.</p>
                    <div class="mt-8 grid gap-3 sm:grid-cols-2">
                        @foreach (['Acceso por usuario', 'Permisos por rol', 'Separación por clínica', 'Rutas protegidas', 'Validaciones de datos', 'Información sensible restringida'] as $item)
                            <div class="flex items-center gap-2 rounded-xl bg-white/10 px-3 py-3 text-sm font-semibold"><svg class="h-4 w-4 shrink-0 text-[#38BDF8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>{{ $item }}</div>
                        @endforeach
                    </div>
                </div>
                <div><p class="text-sm font-bold uppercase tracking-wider text-[#2563EB]">Seguridad y confianza</p><h2 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Control real sin promesas legales que el producto no pueda demostrar</h2><p class="mt-5 text-base leading-7 text-[#475569]">La plataforma aplica controles técnicos concretos: usuarios autenticados, permisos Spatie, aislamiento mediante clínica y validación de información antes de guardar.</p><p class="mt-4 text-sm leading-6 text-[#64748B]">La seguridad del sistema se presenta con transparencia, sin atribuir certificaciones o cumplimientos regulatorios que no hayan sido implementados y verificados.</p></div>
            </div>
        </section>

        <section id="reportes" class="scroll-mt-20 bg-[#F8FAFC] py-20 lg:py-28">
            <div data-reveal class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
                    <div><p class="text-sm font-bold uppercase tracking-wider text-[#2563EB]">Reportes y analítica</p><h2 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Convierte la actividad diaria en información útil</h2><p class="mt-5 text-base leading-7 text-[#475569]">Consulta indicadores basados en los datos reales registrados por tu equipo y detecta con rapidez qué necesita atención.</p><div class="mt-8 grid gap-3 sm:grid-cols-2">@foreach($reports as $report)<div class="flex items-center gap-3 rounded-xl border border-[#E2E8F0] bg-white px-4 py-3 text-sm font-bold shadow-sm"><span class="h-2 w-2 rounded-full bg-[#10B981]"></span>{{ $report }}</div>@endforeach</div></div>
                    <div class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-xl"><div class="flex items-center justify-between"><div><p class="text-sm font-bold">Ingresos por período</p><p class="mt-1 text-xs text-[#475569]">Pagos registrados como completados</p></div><span class="rounded-lg bg-[#10B981]/10 px-3 py-1.5 text-xs font-bold text-[#047857]">+14.2%</span></div><div class="mt-8 flex h-56 items-end gap-3">@foreach([42,58,48,72,66,86,78,96,74,88,82,100] as $index => $height)<div class="group relative flex-1 rounded-t-md {{ $index === 11 ? 'bg-[#2563EB]' : 'bg-[#38BDF8]/40' }}" style="height: {{ $height }}%"><span class="absolute -top-7 left-1/2 hidden -translate-x-1/2 rounded bg-[#0F172A] px-2 py-1 text-[10px] text-white group-hover:block">{{ $height }}</span></div>@endforeach</div><div class="mt-4 flex justify-between text-[10px] font-semibold text-[#94A3B8]"><span>Ene</span><span>Mar</span><span>May</span><span>Jul</span><span>Sep</span><span>Dic</span></div></div>
                </div>
            </div>
        </section>

        <section id="planes" class="scroll-mt-20 bg-white py-20 lg:py-28">
            <div data-reveal class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-3xl text-center"><p class="text-sm font-bold uppercase tracking-wider text-[#2563EB]">Planes</p><h2 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Una propuesta para cada etapa de tu consultorio</h2><p class="mt-5 text-base leading-7 text-[#475569]">Planes referenciales para conocer tus necesidades. La contratación y los pagos en línea se incorporarán en una fase futura.</p></div>
                <div class="mt-12 grid gap-6 lg:grid-cols-3 lg:items-stretch">
                    @foreach ($plans as $plan)
                        <article class="public-card relative flex flex-col rounded-3xl border p-7 {{ $plan['featured'] ? 'border-[#2563EB] bg-[#0F172A] text-white shadow-2xl shadow-blue-900/20' : 'border-[#E2E8F0] bg-white shadow-sm' }}">
                            @if($plan['featured'])<span class="absolute right-5 top-5 rounded-full bg-[#38BDF8] px-3 py-1 text-xs font-extrabold text-[#0F172A]">Recomendado</span>@endif
                            <h3 class="text-xl font-extrabold">{{ $plan['name'] }}</h3><p class="mt-3 min-h-12 text-sm leading-6 {{ $plan['featured'] ? 'text-slate-300' : 'text-[#475569]' }}">{{ $plan['description'] }}</p>
                            <div class="my-6 border-t {{ $plan['featured'] ? 'border-white/10' : 'border-[#E2E8F0]' }}"></div>
                            <ul class="flex-1 space-y-3">@foreach($plan['features'] as $feature)<li class="flex items-center gap-3 text-sm font-semibold"><svg class="h-4 w-4 shrink-0 text-[#10B981]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>{{ $feature }}</li>@endforeach</ul>
                            <a href="#contacto" class="public-action mt-8 rounded-xl px-5 py-3 text-center text-sm font-bold {{ $plan['featured'] ? 'bg-[#2563EB] text-white hover:bg-blue-500' : 'border border-[#E2E8F0] text-[#0F172A] hover:border-[#2563EB] hover:text-[#2563EB]' }}">Solicitar demo</a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="bg-[#F8FAFC] py-20 lg:py-28">
            <div data-reveal class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="text-center"><p class="text-sm font-bold uppercase tracking-wider text-[#2563EB]">Preguntas frecuentes</p><h2 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Respuestas claras antes de comenzar</h2></div>
                <div class="mt-10 space-y-3">
                    @foreach ($faqs as $faq)
                        <article x-data="{ open: false }" class="public-card rounded-2xl border border-[#E2E8F0] bg-white shadow-sm">
                            <button type="button" class="public-action flex w-full items-center justify-between gap-4 px-5 py-5 text-left" @click="open = !open" :aria-expanded="open"><span class="font-bold">{{ $faq['question'] }}</span><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#2563EB]/10 text-[#2563EB]"><svg class="h-4 w-4 transition-transform duration-300" :class="open && 'rotate-45'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg></span></button>
                            <div x-cloak x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1" class="px-5 pb-5 text-sm leading-7 text-[#475569]">{{ $faq['answer'] }}</div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="contacto" class="scroll-mt-20 bg-white py-20 lg:py-28">
            <div data-reveal class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-3xl bg-[#0F172A] shadow-2xl shadow-slate-900/20">
                    <div class="grid lg:grid-cols-[.85fr_1.15fr]">
                        <div class="relative overflow-hidden p-7 text-white sm:p-10 lg:p-12"><div class="absolute -bottom-24 -left-24 h-64 w-64 rounded-full bg-[#2563EB]/30 blur-3xl"></div><div class="relative"><p class="text-sm font-bold uppercase tracking-wider text-[#38BDF8]">Solicita una demo</p><h2 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Conoce cómo MediFlow puede ordenar tu operación</h2><p class="mt-5 text-sm leading-7 text-slate-300">Cuéntanos sobre tu consultorio y nuestro equipo revisará tus necesidades para contactarte.</p><div class="mt-8 space-y-4">@foreach(['Recorrido por los módulos', 'Revisión de necesidades', 'Orientación para implementación'] as $item)<div class="flex items-center gap-3 text-sm font-semibold"><span class="grid h-8 w-8 place-items-center rounded-lg bg-white/10 text-[#38BDF8]"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg></span>{{ $item }}</div>@endforeach</div></div></div>
                        <form method="POST" action="{{ route('demo-requests.store') }}" class="relative bg-white p-7 sm:p-10 lg:p-12">
                            @csrf

                            <div class="absolute left-[-10000px] top-auto h-px w-px overflow-hidden" aria-hidden="true">
                                <label for="website">Sitio web</label>
                                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                            </div>

                            @if (session('success'))
                                <div class="mb-6 rounded-xl border border-[#10B981]/20 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
                            @endif

                            @if ($errors->any())
                                <div class="mb-6 rounded-xl border border-[#EF4444]/20 bg-[#EF4444]/10 px-4 py-3 text-sm font-semibold text-[#B91C1C]">Revisa los campos marcados antes de enviar la solicitud.</div>
                            @endif

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="demo-name" class="mb-2 block text-sm font-bold">Nombre completo</label>
                                    <input id="demo-name" name="full_name" type="text" value="{{ old('full_name') }}" required maxlength="255" autocomplete="name" class="w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] focus:border-[#2563EB] focus:ring-[#2563EB]" placeholder="Tu nombre">
                                    @error('full_name')<p class="mt-2 text-xs font-semibold text-[#EF4444]">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="demo-email" class="mb-2 block text-sm font-bold">Correo</label>
                                    <input id="demo-email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" class="w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] focus:border-[#2563EB] focus:ring-[#2563EB]" placeholder="correo@consultorio.com">
                                    @error('email')<p class="mt-2 text-xs font-semibold text-[#EF4444]">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="demo-phone" class="mb-2 block text-sm font-bold">Teléfono</label>
                                    <input id="demo-phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" class="w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] focus:border-[#2563EB] focus:ring-[#2563EB]" placeholder="Número de contacto">
                                    @error('phone')<p class="mt-2 text-xs font-semibold text-[#EF4444]">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="clinic-type" class="mb-2 block text-sm font-bold">Tipo de consultorio</label>
                                    <select id="clinic-type" name="clinic_type" class="w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] focus:border-[#2563EB] focus:ring-[#2563EB]">
                                        <option value="">Selecciona una opción</option>
                                        @foreach (\App\Models\DemoRequest::CLINIC_TYPES as $value => $label)<option value="{{ $value }}" @selected(old('clinic_type') === $value)>{{ $label }}</option>@endforeach
                                    </select>
                                    @error('clinic_type')<p class="mt-2 text-xs font-semibold text-[#EF4444]">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="doctors-count" class="mb-2 block text-sm font-bold">Cantidad de médicos</label>
                                    <select id="doctors-count" name="doctors_count" class="w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] focus:border-[#2563EB] focus:ring-[#2563EB]">
                                        <option value="">Selecciona una opción</option>
                                        @foreach (\App\Models\DemoRequest::DOCTORS_COUNTS as $value => $label)<option value="{{ $value }}" @selected(old('doctors_count') === $value)>{{ $label }}</option>@endforeach
                                    </select>
                                    @error('doctors_count')<p class="mt-2 text-xs font-semibold text-[#EF4444]">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="interest-module" class="mb-2 block text-sm font-bold">Principal interés</label>
                                    <select id="interest-module" name="interest_module" class="w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] focus:border-[#2563EB] focus:ring-[#2563EB]">
                                        <option value="">Selecciona una opción</option>
                                        @foreach (\App\Models\DemoRequest::INTEREST_MODULES as $value => $label)<option value="{{ $value }}" @selected(old('interest_module') === $value)>{{ $label }}</option>@endforeach
                                    </select>
                                    @error('interest_module')<p class="mt-2 text-xs font-semibold text-[#EF4444]">{{ $message }}</p>@enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="demo-message" class="mb-2 block text-sm font-bold">Mensaje</label>
                                    <textarea id="demo-message" name="message" rows="4" maxlength="3000" class="w-full rounded-xl border-[#E2E8F0] bg-[#F8FAFC] focus:border-[#2563EB] focus:ring-[#2563EB]" placeholder="Cuéntanos qué necesitas mejorar">{{ old('message') }}</textarea>
                                    @error('message')<p class="mt-2 text-xs font-semibold text-[#EF4444]">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <button type="submit" class="public-action mt-6 w-full rounded-xl bg-[#2563EB] px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-500/20 hover:bg-blue-700">Solicitar demo</button>
                            <p class="mt-3 text-center text-xs leading-5 text-[#475569]">Usaremos estos datos únicamente para responder tu solicitud de información.</p>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('public.partials.footer')

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const elements = document.querySelectorAll('[data-reveal]');
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (reducedMotion || !('IntersectionObserver' in window)) {
                elements.forEach((element) => element.classList.add('is-visible'));
                return;
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            }, {
                threshold: 0.12,
                rootMargin: '0px 0px -48px 0px',
            });

            elements.forEach((element) => observer.observe(element));
        });
    </script>
</body>
</html>
