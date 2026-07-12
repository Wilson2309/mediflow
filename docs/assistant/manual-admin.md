# Manual del Asistente MediFlow — Administración

> Archivo generado. No editar manualmente: su fuente es `resources/assistant/knowledge-base.json`.

Entradas autorizadas para el rol `administrador`.
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

## Pagos pendientes

- Módulo: Pagos
- Pregunta: ¿Cómo veo los pagos pendientes?
- Requiere conexión: Sí
- Ruta relacionada: payments.index

Abre Pagos y Finanzas. Sin filtros, MediFlow muestra una cola de pagos pendientes; también puedes filtrar por estado Pendiente.

Pasos:

1. Abre Pagos y Finanzas.
2. Usa el filtro Estado: Pendiente.
3. Abre el pago que vas a revisar.

Restricciones: Sin restricciones adicionales documentadas.

## Registrar cobro

- Módulo: Pagos
- Pregunta: ¿Cómo registro un cobro?
- Requiere conexión: Sí
- Ruta relacionada: payments.index

Para registrar un cobro sigue estos pasos:

Pasos:

1. Abre Pagos y Finanzas.
2. Localiza el pago pendiente o pulsa Nuevo pago.
3. Verifica paciente, cita, servicio y monto.
4. Selecciona método y estado Pagado.
5. Confirma la fecha y guarda.

Restricciones: Los pagos no se pueden registrar ni modificar sin conexión para evitar duplicaciones o inconsistencias.

## Método, fecha y estado del pago

- Módulo: Pagos
- Pregunta: ¿Qué métodos, fecha y estados admite un pago?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Los métodos son efectivo, tarjeta, transferencia u otro. Los estados son pendiente, pagado, cancelado y reembolsado. Al marcar Pagado sin fecha, MediFlow asigna la fecha y hora local actual.

Restricciones: Sin restricciones adicionales documentadas.

## Ficha del pago

- Módulo: Pagos
- Pregunta: ¿Cómo veo la ficha de un pago?
- Requiere conexión: No
- Ruta relacionada: payments.index

Abre el pago desde el listado. La ficha muestra la información autorizada y las opciones de recibo; el backend verifica que pertenezca a la clínica activa.

Restricciones: Sin restricciones adicionales documentadas.

## Descargar o imprimir recibo

- Módulo: Recibos, Pagos
- Pregunta: ¿Cómo descargo o imprimo un recibo?
- Requiere conexión: Sí
- Ruta relacionada: payments.index

Abre el detalle del pago y utiliza la opción Recibo o Imprimir. Verifica que el pago y la clínica sean correctos antes de generar el documento.

Pasos:

1. Abre la ficha del pago.
2. Selecciona Descargar recibo PDF o Imprimir.
3. Revisa el número REC y la clínica.

Restricciones: Sin restricciones adicionales documentadas.

## Reporte financiero

- Módulo: Reportes, Pagos
- Pregunta: ¿Cómo consulto el reporte financiero y sus filtros?
- Requiere conexión: Sí
- Ruta relacionada: reports.financial

El reporte financiero usa la clínica activa y permite filtrar por rango de fechas, estado, método de pago, servicio y médico. Incluye totales pagados, pendientes y agrupaciones por método.

Pasos:

1. Abre Reportes.
2. Entra en Reporte financiero.
3. Define el rango y filtros.
4. Revisa la clínica y los totales.

Restricciones: Sin restricciones adicionales documentadas.

## Exportar reporte financiero

- Módulo: Reportes
- Pregunta: ¿Cómo exporto el reporte financiero?
- Requiere conexión: Sí
- Ruta relacionada: reports.financial

Desde Reporte financiero puedes exportar PDF, CSV, Excel XLSX o abrir la vista de impresión. Las exportaciones respetan los filtros y la clínica activa.

Pasos:

1. Aplica filtros.
2. Revisa resultados.
3. Elige PDF, CSV, Excel XLSX o Imprimir.

Restricciones: No se pueden generar reportes sin conexión.

## Exportar Excel no visible

- Módulo: Reportes, Permisos
- Pregunta: ¿Por qué no aparece Exportar Excel?
- Requiere conexión: No
- Ruta relacionada: Ninguna

La opción XLSX existe en el reporte financiero, de citas y clínico, pero cada uno exige su permiso específico. También puede estar bloqueada mientras no haya conexión.

Pasos:

1. Confirma que estás en el reporte correcto.
2. Revisa el estado de conexión.
3. Contacta al administrador para validar el permiso.

Restricciones: Sin restricciones adicionales documentadas.

Escalado: Indica el nombre del reporte y tu rol; no compartas su contenido.

## Registro financiero

- Módulo: Registro financiero
- Pregunta: ¿Cómo reviso el registro financiero?
- Requiere conexión: Sí
- Ruta relacionada: financial-audit.index

Abre Registro de caja para revisar eventos financieros auditados de la clínica activa, con filtros disponibles. Esta vista no concede permiso para modificar pagos.

Restricciones: Sin restricciones adicionales documentadas.

## Pagos sin conexión

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

## Iniciar consulta

- Módulo: Consultas, Agenda del día
- Pregunta: ¿Cómo inicio una consulta?
- Requiere conexión: Sí
- Ruta relacionada: daily-agenda.index

Localiza una cita válida en Agenda del día y usa Iniciar consulta. El médico solo puede atender sus citas y necesita un pago con estado Pagado; el administrador tiene la excepción implementada para pago previo.

Pasos:

1. Abre Agenda del día.
2. Localiza la cita asignada.
3. Verifica que no esté cancelada, no asistió ni atendida.
4. Confirma el pago cuando aplique.
5. Pulsa Iniciar consulta.

Restricciones: El envío de la consulta al servidor requiere conexión; sin conexión puede conservarse un borrador.

## Guardar y completar consulta

- Módulo: Consultas
- Pregunta: ¿Cómo guardo o completo una consulta?
- Requiere conexión: Sí
- Ruta relacionada: consultations.index

Completa el formulario autorizado y guarda. Al registrar una consulta vinculada, MediFlow marca como completada una cita programada o confirmada.

Pasos:

1. Revisa paciente, médico y cita.
2. Completa motivo, diagnóstico, tratamiento y observaciones permitidas.
3. Guarda con conexión.

Restricciones: Sin conexión se guarda un borrador local, no una consulta definitiva.

## Borrador clínico

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

## Ver historia clínica

- Módulo: Historias clínicas
- Pregunta: ¿Cómo consulto una historia clínica?
- Requiere conexión: Sí
- Ruta relacionada: medical-records.index

Abre Historial clínico, busca el registro permitido y entra en su ficha. La vista reúne el registro y actividad reciente del paciente dentro de la clínica activa.

Restricciones: Sin restricciones adicionales documentadas.

## Crear o actualizar historia clínica

- Módulo: Historias clínicas
- Pregunta: ¿Cómo creo o actualizo un registro médico?
- Requiere conexión: Sí
- Ruta relacionada: medical-records.index

Cada paciente puede tener una historia clínica. El formulario permite antecedentes personales, familiares y quirúrgicos, alergias, hábitos, medicamentos actuales, enfermedades crónicas y observaciones.

Pasos:

1. Abre Historial clínico.
2. Crea un registro para un paciente sin historia o abre Editar.
3. Completa solo información autorizada.
4. Revisa y guarda.

Restricciones: Sin conexión solo se conserva un borrador local.

## Borrador de historia clínica

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

## Crear receta

- Módulo: Recetas
- Pregunta: ¿Cómo creo una receta médica?
- Requiere conexión: Sí
- Ruta relacionada: prescriptions.create

Abre Recetas médicas y pulsa Nueva receta. También puede iniciarse con una consulta preseleccionada. Completa medicamentos e indicaciones, revisa y guarda.

Pasos:

1. Abre Recetas médicas.
2. Pulsa Nueva receta.
3. Selecciona consulta, paciente y médico coherentes.
4. Añade medicamentos e indicaciones.
5. Guarda la receta.

Restricciones: Sin conexión se conserva un borrador; firmar y enviar por correo siempre requieren conexión.

## Editar receta

- Módulo: Recetas
- Pregunta: ¿Puedo editar una receta?
- Requiere conexión: Sí
- Ruta relacionada: prescriptions.index

Sí, mientras no esté firmada. Una receta firmada no puede editarse; debe anularse o crearse una nueva según corresponda.

Pasos:

1. Abre la receta.
2. Si no está firmada, pulsa Editar.
3. Revisa y guarda los cambios.

Restricciones: Sin restricciones adicionales documentadas.

## Firmar receta

- Módulo: Recetas
- Pregunta: ¿Cómo firmo una receta?
- Requiere conexión: Sí
- Ruta relacionada: prescriptions.index

Abre una receta activa y sin firma, revisa su contenido y pulsa Firmar. Es una firma electrónica interna verificable de MediFlow; después de firmar no puede editarse.

Pasos:

1. Abre el detalle.
2. Revisa receta e ítems.
3. Pulsa Firmar.
4. Confirma la acción.

Restricciones: La firma requiere conexión y se bloquea para recetas canceladas o ya firmadas.

## Verificación y QR de receta

- Módulo: Recetas
- Pregunta: ¿Cómo funciona el código de verificación y el QR?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Al firmar, MediFlow genera un código y una huella interna. El PDF o impresión puede incluir un QR hacia la ruta pública de verificación, que indica si la firma es válida, fue alterada o no existe.

Restricciones: Sin restricciones adicionales documentadas.

## PDF e impresión de receta

- Módulo: Recetas
- Pregunta: ¿Cómo descargo o imprimo una receta?
- Requiere conexión: Sí
- Ruta relacionada: prescriptions.index

Desde el detalle usa Descargar PDF o Imprimir. MediFlow registra la entrega y muestra la firma interna y QR cuando la receta está firmada.

Pasos:

1. Abre la receta.
2. Elige PDF o Imprimir.
3. Revisa el documento generado.

Restricciones: Sin restricciones adicionales documentadas.

## Enviar receta por correo

- Módulo: Recetas
- Pregunta: ¿Cómo envío una receta por correo?
- Requiere conexión: Sí
- Ruta relacionada: prescriptions.index

Abre la receta, revisa el correo del paciente o ingresa uno válido y usa Enviar. MediFlow adjunta el PDF y registra el último envío.

Pasos:

1. Abre el detalle.
2. Confirma el destinatario.
3. Pulsa Enviar por correo.
4. Revisa el mensaje de resultado.

Restricciones: El envío requiere conexión y una configuración de correo funcional.

## Recetas sin conexión

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

## Dashboard administrativo

- Módulo: Dashboard
- Pregunta: ¿Qué muestra el Dashboard administrativo?
- Requiere conexión: Sí
- Ruta relacionada: dashboard

El Dashboard calcula métricas de la clínica activa según permisos: pacientes, médicos, citas de hoy, consultas, recetas activas, ingresos pagados, pagos pendientes, servicios, usuarios y próximas citas.

Restricciones: Sin restricciones adicionales documentadas.

## Médicos y servicios

- Módulo: Médicos, Servicios
- Pregunta: ¿Cómo gestiono médicos y servicios?
- Requiere conexión: Sí
- Ruta relacionada: services.index

Los módulos Médicos y Servicios ofrecen listados y CRUD según permisos. Los servicios incluyen precio, duración, estado y asociación con médicos, usada por la agenda.

Pasos:

1. Abre el módulo correspondiente.
2. Crea o edita el registro.
3. Revisa estado y, en Servicios, médicos asociados.
4. Guarda con conexión.

Restricciones: Sin restricciones adicionales documentadas.

## Usuarios y roles

- Módulo: Usuarios y roles
- Pregunta: ¿Cómo creo o edito usuarios y roles?
- Requiere conexión: Sí
- Ruta relacionada: users.index

En Usuarios y Roles puedes crear o editar cuentas con los roles administrador, médico, recepcionista o caja_finanzas. SuperAdmin no se asigna desde este módulo.

Pasos:

1. Abre Usuarios y Roles.
2. Crea o edita una cuenta.
3. Selecciona rol y estado.
4. Si es médico, completa su perfil.
5. Guarda.

Restricciones: La gestión de usuarios se bloquea sin conexión.

## Asignar clínicas a usuarios

- Módulo: Usuarios y roles, Cambio de clínica
- Pregunta: ¿Cómo asigno clínicas a un usuario?
- Requiere conexión: Sí
- Ruta relacionada: Ninguna

El formulario permite asignar únicamente clínicas activas que el administrador actual también administra. La clínica activa actual siempre se conserva en la asignación.

Pasos:

1. Abre el usuario.
2. Selecciona clínicas disponibles.
3. Guarda.
4. Si falta una clínica, revisa tu propio acceso o su estado.

Restricciones: Sin restricciones adicionales documentadas.

Escalado: Si la clínica no aparece, solicita a SuperAdmin revisar su estado o tu asociación.

## Configuración de clínica

- Módulo: Configuración de clínica
- Pregunta: ¿Cómo actualizo la configuración de la clínica?
- Requiere conexión: Sí
- Ruta relacionada: settings.clinic.edit

Abre Configuración, confirma la clínica activa y modifica los campos autorizados. El controlador siempre actualiza la clínica resuelta para el usuario autenticado.

Pasos:

1. Abre Configuración.
2. Revisa la clínica mostrada.
3. Actualiza datos o logo.
4. Guarda con conexión.

Restricciones: Los cambios administrativos se bloquean sin conexión.

## Auditoría de actividad

- Módulo: Auditoría
- Pregunta: ¿Cómo reviso la auditoría?
- Requiere conexión: Sí
- Ruta relacionada: audit-logs.index

Abre Auditoría para revisar eventos de la clínica activa con filtros por búsqueda, módulo, acción, usuario y fechas. La vista está protegida por audit_logs.view.

Restricciones: Sin restricciones adicionales documentadas.

## Reportes administrativos

- Módulo: Reportes
- Pregunta: ¿Qué reportes puede consultar el administrador?
- Requiere conexión: Sí
- Ruta relacionada: reports.index

El administrador dispone de panel de reportes y vistas de citas, clínico, financiero, pacientes, médicos y servicios. PDF, CSV y XLSX están implementados para citas, clínico y financiero; las otras vistas no tienen rutas de exportación dedicadas.

Restricciones: Sin restricciones adicionales documentadas.

## Acciones administrativas sin conexión

- Módulo: Conexión y borradores, Usuarios y roles, Configuración de clínica, Pagos
- Pregunta: ¿Qué acciones administrativas se bloquean sin conexión?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Se bloquean pagos, usuarios, configuración, firma y envío de recetas, exportaciones y otras acciones críticas. Pacientes, citas, consultas, historias y recetas sin firmar pueden conservarar borradores donde el formulario lo indica.

Restricciones: Sin restricciones adicionales documentadas.
