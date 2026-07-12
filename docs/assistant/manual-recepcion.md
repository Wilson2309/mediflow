# Manual del Asistente MediFlow — Recepción

> Archivo generado. No editar manualmente: su fuente es `resources/assistant/knowledge-base.json`.

Entradas autorizadas para el rol `recepcionista`.
## Estados de conexión

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

- Módulo: Conexión y borradores
- Pregunta: ¿Qué es un borrador local y dónde se guarda?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Un borrador local es una copia temporal del formulario guardada en el almacenamiento local del navegador. Se separa por clínica, usuario, rol, formulario y registro; no se envía al servidor.

Restricciones: Sin restricciones adicionales documentadas.

## Restaurar o descartar borrador

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

- Módulo: Conexión y borradores, Cambio de clínica
- Pregunta: ¿Se mezclan borradores entre usuarios o clínicas?
- Requiere conexión: No
- Ruta relacionada: Ninguna

No. La clave local incluye clínica, usuario, rol, formulario y registro. Un borrador no debe aparecer en otro contexto.

Restricciones: Sin restricciones adicionales documentadas.

## Botón no visible

- Módulo: Permisos
- Pregunta: ¿Por qué no aparece un botón o módulo?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Las opciones se muestran según el rol, el permiso efectivo, el estado del registro y la clínica activa. El asistente no puede ampliar permisos.

Pasos:

1. Confirma que estás en la clínica correcta.
2. Revisa si el registro cumple el estado requerido.
3. Contacta al administrador si la función corresponde a tu trabajo.

Restricciones: Sin restricciones adicionales documentadas.

Escalado: Contacta al administrador e indica el módulo y la acción que necesitas, sin compartir datos sensibles.

## Acceso 403

- Módulo: Permisos
- Pregunta: ¿Por qué una ruta muestra 403?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Un 403 indica que el backend rechazó el acceso por permiso, clínica activa, alcance del registro o estado de la clínica. No intentes acceder mediante otra URL.

Pasos:

1. Vuelve al menú autorizado.
2. Confirma la clínica activa.
3. Contacta al administrador si consideras que necesitas ese permiso.

Restricciones: Sin restricciones adicionales documentadas.

Escalado: Comparte con el administrador el módulo y la ruta, nunca información clínica o financiera.

## Cambiar de clínica

- Módulo: Cambio de clínica
- Pregunta: ¿Cómo cambio de clínica?
- Requiere conexión: Sí
- Ruta relacionada: dashboard

Usa el selector Sucursal activa de la navegación. Solo aparecen clínicas activas asociadas a tu usuario.

Pasos:

1. Abre el selector Sucursal activa.
2. Selecciona otra clínica disponible.
3. Espera la recarga y confirma el nombre mostrado.

Restricciones: El cambio requiere comunicación con el servidor.

## Clínica ausente del selector

- Módulo: Cambio de clínica
- Pregunta: ¿Por qué no aparece otra clínica en el selector?
- Requiere conexión: No
- Ruta relacionada: Ninguna

El selector solo lista clínicas activas asociadas al usuario. Si falta una clínica, puede estar inactiva o no estar asignada a tu cuenta.

Pasos:

1. Confirma que usas la cuenta correcta.
2. Contacta al administrador para revisar la asignación y el estado de la clínica.

Restricciones: Sin restricciones adicionales documentadas.

Escalado: Solicita al administrador que revise la asociación de tu usuario; no compartas credenciales.

## Clínica inactiva

- Módulo: Cambio de clínica, Clínicas globales
- Pregunta: ¿Qué pasa si una clínica está inactiva?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Una clínica inactiva no puede seleccionarse como clínica activa y las rutas protegidas por clínica rechazan el acceso. Solo SuperAdmin puede cambiar su estado global.

Pasos:

1. No intentes continuar con otra URL.
2. El personal debe contactar a su administrador.
3. SuperAdmin puede revisar el estado desde el panel global.

Restricciones: Sin restricciones adicionales documentadas.

## Datos separados por clínica

- Módulo: Cambio de clínica
- Pregunta: ¿Los datos se separan por clínica?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Sí. Las consultas y autorizaciones usan la clínica activa. El cambio de clínica modifica el contexto, no combina información entre clínicas.

Restricciones: Sin restricciones adicionales documentadas.

## Soporte seguro

- Módulo: Soporte
- Pregunta: ¿Qué hago si el asistente no encuentra una respuesta?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Usa Copiar detalles para soporte o Contactar administrador. Los detalles incluyen rol, módulo, ruta, conexión y una pregunta segura; revísalos antes de compartirlos.

Pasos:

1. Elimina nombres, identificaciones, diagnósticos, montos y credenciales.
2. Copia los detalles seguros.
3. Compártelos con el administrador.

Restricciones: Sin restricciones adicionales documentadas.

Escalado: No incluyas datos sensibles al solicitar soporte.

## Registrar paciente

- Módulo: Pacientes
- Pregunta: ¿Cómo registro un paciente?
- Requiere conexión: Sí
- Ruta relacionada: patients.create

Para registrar un paciente sigue estos pasos:

Pasos:

1. Abre Pacientes.
2. Pulsa Nuevo paciente.
3. Completa los datos solicitados.
4. Revisa y guarda el registro.

Restricciones: Sin conexión el formulario se conserva como borrador local y no se registra en el servidor.

## Buscar y editar paciente

- Módulo: Pacientes
- Pregunta: ¿Cómo busco o edito un paciente?
- Requiere conexión: Sí
- Ruta relacionada: patients.index

En Pacientes puedes buscar por nombre, identificación, teléfono o correo. Abre la ficha y usa Editar si tienes permiso.

Pasos:

1. Abre Pacientes.
2. Usa el buscador.
3. Abre el registro correcto.
4. Pulsa Editar, revisa y guarda.

Restricciones: La actualización requiere conexión.

## Datos obligatorios del paciente

- Módulo: Pacientes
- Pregunta: ¿Qué datos del paciente son obligatorios?
- Requiere conexión: No
- Ruta relacionada: patients.create

Nombres, apellidos y estado son obligatorios. La identificación, fecha de nacimiento, género, contacto, dirección, tipo de sangre, alergias y contacto de emergencia son opcionales, pero se validan si se completan.

Restricciones: Sin restricciones adicionales documentadas.

## Tipo de sangre

- Módulo: Pacientes
- Pregunta: ¿Cómo registro el tipo de sangre?
- Requiere conexión: No
- Ruta relacionada: Ninguna

El tipo de sangre es opcional y se selecciona entre A+, A-, B+, B-, AB+, AB-, O+ u O-. No escribas un valor distinto a las opciones del formulario.

Restricciones: Sin restricciones adicionales documentadas.

## Identificación duplicada

- Módulo: Pacientes
- Pregunta: ¿Por qué indica identificación duplicada?
- Requiere conexión: No
- Ruta relacionada: Ninguna

La identificación debe ser única. Busca primero al paciente existente y evita crear un segundo registro.

Pasos:

1. Copia solo el valor de identificación necesario para la búsqueda.
2. Busca al paciente en el listado.
3. Si parece un error, contacta al administrador sin compartir la identificación completa.

Restricciones: Sin restricciones adicionales documentadas.

Escalado: Solicita una revisión administrativa sin enviar la identificación completa por canales inseguros.

## Borrador offline de paciente

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

## Paciente no guardado

- Módulo: Pacientes
- Pregunta: ¿Qué hago si el paciente no se guarda?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Revisa los mensajes de validación, la identificación duplicada y el estado de conexión. Sin conexión restaura el borrador y vuelve a enviarlo manualmente.

Pasos:

1. Corrige los campos marcados.
2. Confirma que MediFlow esté Conectado.
3. Busca posibles duplicados.
4. Contacta al administrador si el error continúa.

Restricciones: Sin restricciones adicionales documentadas.

Escalado: Indica el mensaje mostrado y la ruta; no compartas datos del paciente.

## Crear cita

- Módulo: Citas
- Pregunta: ¿Cómo agendo una cita?
- Requiere conexión: Sí
- Ruta relacionada: appointments.create

Para agendar una cita sigue estos pasos:

Pasos:

1. Abre Citas médicas y pulsa Nueva cita.
2. Busca y selecciona al paciente.
3. Elige el servicio.
4. Selecciona un médico compatible.
5. Consulta disponibilidad y elige fecha y hora.
6. Revisa y guarda.

Restricciones: Sin conexión solo se conserva un borrador; la disponibilidad debe comprobarse nuevamente al enviarlo.

## Paciente y servicio de la cita

- Módulo: Citas
- Pregunta: ¿Cómo busco el paciente y elijo el servicio?
- Requiere conexión: No
- Ruta relacionada: appointments.create

En Nueva cita usa la búsqueda de pacientes y luego selecciona un servicio activo. El servicio determina la duración y limita los médicos compatibles.

Pasos:

1. Busca por nombre o identificación.
2. Selecciona el paciente correcto.
3. Elige el servicio antes de buscar médico.

Restricciones: Sin restricciones adicionales documentadas.

## Médicos compatibles

- Módulo: Citas, Médicos, Servicios
- Pregunta: ¿Por qué no aparece un médico al elegir servicio?
- Requiere conexión: No
- Ruta relacionada: Ninguna

La búsqueda solo devuelve médicos activos de la clínica que estén asociados al servicio seleccionado. Sin servicio, la lista de médicos queda vacía.

Pasos:

1. Selecciona primero el servicio.
2. Confirma que el médico esté activo.
3. Prueba otra búsqueda.
4. Contacta al administrador si debe ofrecer ese servicio.

Restricciones: Sin restricciones adicionales documentadas.

Escalado: El administrador puede revisar la relación entre médico y servicio.

## Disponibilidad de citas

- Módulo: Citas
- Pregunta: ¿Cómo funciona la disponibilidad?
- Requiere conexión: Sí
- Ruta relacionada: appointments.create

MediFlow calcula bloques entre 08:00 y 18:00 con la duración del servicio y excluye cruces con citas programadas o confirmadas del médico.

Pasos:

1. Selecciona servicio, médico y fecha.
2. Espera la consulta de disponibilidad.
3. Elige un bloque marcado como disponible.

Restricciones: La disponibilidad se consulta al servidor.

## Sin horarios disponibles

- Módulo: Citas
- Pregunta: ¿Qué hago si no hay horarios disponibles?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Puede que todos los bloques estén ocupados, que el médico no ofrezca el servicio o que la fecha no sea válida. Prueba otro médico compatible o fecha.

Pasos:

1. Confirma servicio y médico.
2. Cambia la fecha.
3. Prueba otro médico compatible.
4. Contacta al administrador si nunca aparecen bloques.

Restricciones: Sin restricciones adicionales documentadas.

Escalado: Solicita revisar la configuración del servicio y del médico.

## Horario ocupado

- Módulo: Citas
- Pregunta: ¿Qué pasa si el horario fue ocupado?
- Requiere conexión: No
- Ruta relacionada: Ninguna

El servidor vuelve a validar el horario al guardar. Si otra cita lo ocupó, debes actualizar disponibilidad y elegir otro bloque.

Pasos:

1. Consulta nuevamente la disponibilidad.
2. Elige otro horario libre.
3. Guarda después de revisar el nuevo bloque.

Restricciones: Sin restricciones adicionales documentadas.

## Reprogramar o cancelar cita

- Módulo: Citas, Agenda del día
- Pregunta: ¿Cómo reprogramo o cancelo una cita?
- Requiere conexión: Sí
- Ruta relacionada: daily-agenda.index

Para reprogramar usa Editar y selecciona un nuevo horario disponible. Para cancelar, usa la acción Cancelar desde Agenda del día y confirma el registro correcto.

Pasos:

1. Localiza la cita.
2. Elige Editar para reprogramar o Cancelar en la agenda.
3. Revisa paciente, fecha y hora.
4. Confirma la acción.

Restricciones: La modificación del estado requiere conexión.

## Marcar no asistió

- Módulo: Agenda del día, Citas
- Pregunta: ¿Cómo marco que el paciente no asistió?
- Requiere conexión: Sí
- Ruta relacionada: daily-agenda.index

Desde Agenda del día usa Marcar no asistió. La acción no se aplica a citas canceladas o completadas.

Pasos:

1. Abre Agenda del día.
2. Localiza la cita.
3. Pulsa Marcar no asistió y confirma.

Restricciones: Sin restricciones adicionales documentadas.

## Agenda del día

- Módulo: Agenda del día, Citas
- Pregunta: ¿Cómo reviso la agenda diaria?
- Requiere conexión: Sí
- Ruta relacionada: daily-agenda.index

Agenda del día muestra las citas de la fecha seleccionada, filtros por estado y estado básico del pago. El médico ve solo citas asignadas a su perfil; los montos dependen del permiso financiero.

Pasos:

1. Abre Agenda del día.
2. Selecciona la fecha.
3. Aplica filtros de cita o pago.
4. Abre el registro permitido.

Restricciones: Sin restricciones adicionales documentadas.

## Estado básico del pago

- Módulo: Citas, Agenda del día, Pagos
- Pregunta: ¿Cómo reviso el estado básico del pago de una cita?
- Requiere conexión: No
- Ruta relacionada: daily-agenda.index

La cita y la agenda muestran el estado asociado: pendiente, pagado, cancelado o reembolsado, y también pueden indicar que no existe pago. Solo caja y administración gestionan cobros.

Restricciones: Sin restricciones adicionales documentadas.

## Borrador offline de cita

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
