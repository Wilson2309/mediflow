# Manual del Asistente MediFlow — Médico

> Archivo generado. No editar manualmente: su fuente es `resources/assistant/knowledge-base.json`.

Entradas autorizadas para el rol `medico`.
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

## Agenda propia del médico

- Módulo: Citas, Agenda del día, Dashboard
- Pregunta: ¿Cómo veo mis citas asignadas?
- Requiere conexión: Sí
- Ruta relacionada: daily-agenda.index

El Dashboard, Citas y Agenda del día limitan los resultados al perfil médico asociado a tu usuario y clínica activa.

Pasos:

1. Abre Agenda del día.
2. Selecciona la fecha.
3. Revisa estado de cita y pago.

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

## Consulta bloqueada

- Módulo: Consultas, Agenda del día
- Pregunta: ¿Por qué no puedo iniciar una consulta?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Las causas verificadas son: cita de otro médico, cita cancelada o no asistió, pago no pagado, o una consulta ya asociada a la cita.

Pasos:

1. Confirma que la cita está asignada a tu perfil.
2. Revisa su estado.
3. Revisa el estado básico del pago.
4. Comprueba que no figure como atendida.
5. Contacta al administrador si persiste.

Restricciones: Sin restricciones adicionales documentadas.

Escalado: Indica el número interno de la cita sin incluir datos clínicos.

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

## Reportes propios del médico

- Módulo: Reportes
- Pregunta: ¿Qué reportes puede ver un médico?
- Requiere conexión: Sí
- Ruta relacionada: reports.index

El médico puede consultar el reporte de citas y el reporte clínico. El backend los limita al perfil médico asociado a su usuario, sin aceptar un filtro para otro médico.

Pasos:

1. Abre Reportes.
2. Elige Citas o Clínico.
3. Aplica rango y filtros permitidos.

Restricciones: Sin restricciones adicionales documentadas.

## Exportar reportes propios

- Módulo: Reportes
- Pregunta: ¿Cómo exporto mis reportes de citas o clínicos?
- Requiere conexión: Sí
- Ruta relacionada: reports.index

En el reporte de citas o clínico aplica filtros y elige PDF, CSV, Excel XLSX o impresión. La exportación conserva el alcance de tu perfil médico.

Pasos:

1. Abre el reporte permitido.
2. Define el rango.
3. Revisa resultados.
4. Elige el formato.

Restricciones: Las exportaciones se generan en el servidor.

## Sin acceso financiero del médico

- Módulo: Reportes, Pagos, Permisos
- Pregunta: ¿Por qué no veo pagos ni reportes financieros?
- Requiere conexión: No
- Ruta relacionada: Ninguna

El rol médico no tiene permisos de pagos ni reporte financiero. Puede ver el estado básico del pago vinculado a sus citas, pero no cobrar ni consultar montos financieros completos.

Restricciones: Sin restricciones adicionales documentadas.
