# Manual de conexión y borradores

> Archivo generado. No editar manualmente: su fuente es `resources/assistant/knowledge-base.json`.

Entradas sobre estados de conexión, almacenamiento local y acciones bloqueadas.
## Estados de conexión

- Rol: Administración, Recepción, Caja y finanzas, Médico, Súper Admin
- Módulo: Conexión y borradores
- Pregunta: ¿Qué significan los estados de conexión?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Conectado indica que MediFlow y el acceso a Internet responden. Sin Internet indica que el servidor local responde, pero no hay salida a Internet. Servidor no disponible indica que MediFlow no responde. Sin conexión indica que el navegador tampoco tiene red.

Pasos:

1. Revisa el indicador del encabezado.
2. Conserva la pantalla abierta si necesitas consultar información ya cargada.
3. Espera a que el estado vuelva a Conectado antes de ejecutar acciones bloqueadas.

Restricciones: Sin restricciones adicionales documentadas.

## Conexión restablecida

- Rol: Administración, Recepción, Caja y finanzas, Médico, Súper Admin
- Módulo: Conexión y borradores
- Pregunta: ¿Qué hago cuando vuelve la conexión?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Cuando el indicador vuelva a Conectado, revisa la información antes de enviarla. Los borradores no se sincronizan solos y las acciones críticas deben repetirse manualmente.

Pasos:

1. Confirma que el indicador muestre Conectado.
2. Restaura el borrador si existe.
3. Revisa datos y disponibilidad actuales.
4. Envía o confirma la acción manualmente.

Restricciones: Sin restricciones adicionales documentadas.

## Borradores locales

- Rol: Administración, Recepción, Médico
- Módulo: Conexión y borradores
- Pregunta: ¿Qué es un borrador local y dónde se guarda?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Un borrador local es una copia temporal del formulario guardada en el almacenamiento local del navegador. Se separa por clínica, usuario, rol, formulario y registro; no se envía al servidor.

Restricciones: Sin restricciones adicionales documentadas.

## Restaurar o descartar borrador

- Rol: Administración, Recepción, Médico
- Módulo: Conexión y borradores
- Pregunta: ¿Cómo restauro o descarto un borrador?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Al abrir el mismo formulario, MediFlow muestra un aviso si existe un borrador. Restaurar repone los campos guardados; Descartar elimina esa copia local.

Pasos:

1. Abre el mismo formulario y registro.
2. Pulsa Restaurar borrador para recuperar los campos o Descartar borrador para eliminarlo.
3. Si restauras, revisa todo antes de enviarlo.

Restricciones: Sin restricciones adicionales documentadas.

## Ciclo de vida del borrador

- Rol: Administración, Recepción, Médico
- Módulo: Conexión y borradores
- Pregunta: ¿Cuándo se elimina o sincroniza un borrador?
- Requiere conexión: No
- Ruta relacionada: Ninguna

El borrador se elimina al descartarlo o al enviar el formulario mientras MediFlow está conectado. No se sincroniza automáticamente.

Pasos:

1. Restaura el borrador.
2. Revisa la información.
3. Envíalo manualmente con conexión.

Restricciones: Sin restricciones adicionales documentadas.

## Aislamiento de borradores

- Rol: Administración, Recepción, Médico
- Módulo: Conexión y borradores, Cambio de clínica
- Pregunta: ¿Se mezclan borradores entre usuarios o clínicas?
- Requiere conexión: No
- Ruta relacionada: Ninguna

No. La clave local incluye clínica, usuario, rol, formulario y registro. Un borrador no debe aparecer en otro contexto.

Restricciones: Sin restricciones adicionales documentadas.

## Borrador offline de paciente

- Rol: Administración, Recepción
- Módulo: Pacientes, Conexión y borradores
- Pregunta: ¿Cómo guardo y recupero un borrador de paciente?
- Requiere conexión: No
- Ruta relacionada: patients.create

El formulario de paciente admite borrador local. Sin conexión se guarda en el navegador; al volver debes abrir el mismo formulario, restaurarlo, revisarlo y enviarlo manualmente.

Pasos:

1. Completa el formulario.
2. Si no hay conexión, deja que MediFlow guarde el borrador.
3. Vuelve al mismo formulario.
4. Elige Restaurar o Descartar.

Restricciones: Sin restricciones adicionales documentadas.

## Borrador offline de cita

- Rol: Administración, Recepción
- Módulo: Citas, Conexión y borradores
- Pregunta: ¿Cómo funciona el borrador offline de una cita?
- Requiere conexión: No
- Ruta relacionada: appointments.create

El formulario de cita puede guardarse como borrador local. Al restaurarlo debes volver a consultar disponibilidad porque el horario pudo ocuparse.

Pasos:

1. Restaura el borrador en el mismo formulario.
2. Revisa paciente, servicio y médico.
3. Consulta otra vez la disponibilidad.
4. Elige un bloque libre y envía manualmente.

Restricciones: Sin restricciones adicionales documentadas.

## Pagos sin conexión

- Rol: Administración, Caja y finanzas
- Módulo: Pagos, Conexión y borradores
- Pregunta: ¿Por qué no puedo cobrar sin Internet?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Los pagos no se pueden registrar ni modificar sin conexión para evitar duplicaciones o inconsistencias.

Pasos:

1. Conserva la pantalla ya cargada solo para consulta.
2. Espera a que el indicador muestre Conectado.
3. Vuelve a verificar el estado del pago antes de cobrar.

Restricciones: Sin restricciones adicionales documentadas.

## Borrador clínico

- Rol: Administración, Médico
- Módulo: Consultas, Conexión y borradores
- Pregunta: ¿Cómo recupero un borrador clínico?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Abre el mismo formulario. Si existe un borrador de tu usuario, clínica y registro, pulsa Restaurar, revisa cuidadosamente y envíalo manualmente con conexión.

Pasos:

1. Abre el mismo formulario.
2. Pulsa Restaurar borrador.
3. Revisa todos los campos clínicos.
4. Envía manualmente cuando vuelva la conexión.

Restricciones: Sin restricciones adicionales documentadas.

## Borrador de historia clínica

- Rol: Administración, Médico
- Módulo: Historias clínicas, Conexión y borradores
- Pregunta: ¿Puedo guardar una historia clínica sin conexión?
- Requiere conexión: No
- Ruta relacionada: Ninguna

El formulario admite borrador local separado por usuario y clínica. No crea ni actualiza la historia en el servidor hasta que lo restaures y envíes manualmente.

Pasos:

1. Restaura en el mismo formulario.
2. Revisa alergias, hábitos y demás campos.
3. Envía con conexión.

Restricciones: Sin restricciones adicionales documentadas.

## Recetas sin conexión

- Rol: Administración, Médico
- Módulo: Recetas, Conexión y borradores
- Pregunta: ¿Por qué no puedo firmar o enviar una receta sin conexión?
- Requiere conexión: No
- Ruta relacionada: Ninguna

MediFlow bloquea la firma y el envío por correo sin conexión porque son acciones oficiales. Puedes conservar un borrador local y completarlas cuando se restablezca la conexión.

Pasos:

1. Guarda o conserva el borrador.
2. Espera el estado Conectado.
3. Restaura y revisa.
4. Firma o envía manualmente.

Restricciones: Sin restricciones adicionales documentadas.

## Acciones administrativas sin conexión

- Rol: Administración
- Módulo: Conexión y borradores, Usuarios y roles, Configuración de clínica, Pagos
- Pregunta: ¿Qué acciones administrativas se bloquean sin conexión?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Se bloquean pagos, usuarios, configuración, firma y envío de recetas, exportaciones y otras acciones críticas. Pacientes, citas, consultas, historias y recetas sin firmar pueden conservarar borradores donde el formulario lo indica.

Restricciones: Sin restricciones adicionales documentadas.

## Panel global sin conexión

- Rol: Súper Admin
- Módulo: Clínicas globales, Suscripciones, Onboarding SaaS, Conexión y borradores
- Pregunta: ¿Qué acciones de SuperAdmin se bloquean sin conexión?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Crear o editar clínicas, cambiar estado, configurar suscripción y completar onboarding requieren conexión. El asistente solo explica estas funciones y no ejecuta cambios.

Restricciones: Sin restricciones adicionales documentadas.
