# Manual del Asistente MediFlow — Caja y finanzas

> Archivo generado. No editar manualmente: su fuente es `resources/assistant/knowledge-base.json`.

Entradas autorizadas para el rol `caja_finanzas`.
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
