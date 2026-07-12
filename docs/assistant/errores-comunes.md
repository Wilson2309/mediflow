# Errores comunes del Asistente MediFlow

> Archivo generado. No editar manualmente: su fuente es `resources/assistant/knowledge-base.json`.

Causas posibles, comprobaciones seguras y escalado para usuarios finales.
## Botón no visible

- Rol: Administración, Recepción, Caja y finanzas, Médico, Súper Admin
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

- Rol: Administración, Recepción, Caja y finanzas, Médico, Súper Admin
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

## Clínica ausente del selector

- Rol: Administración, Recepción, Caja y finanzas, Médico
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

- Rol: Administración, Recepción, Caja y finanzas, Médico, Súper Admin
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

## Soporte seguro

- Rol: Administración, Recepción, Caja y finanzas, Médico, Súper Admin
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

## Identificación duplicada

- Rol: Administración, Recepción
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

## Paciente no guardado

- Rol: Administración, Recepción
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

## Médicos compatibles

- Rol: Administración, Recepción
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

## Sin horarios disponibles

- Rol: Administración, Recepción
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

- Rol: Administración, Recepción
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

## Exportar Excel no visible

- Rol: Administración, Caja y finanzas
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

## Consulta bloqueada

- Rol: Médico
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

## Sin acceso financiero del médico

- Rol: Médico
- Módulo: Reportes, Pagos, Permisos
- Pregunta: ¿Por qué no veo pagos ni reportes financieros?
- Requiere conexión: No
- Ruta relacionada: Ninguna

El rol médico no tiene permisos de pagos ni reporte financiero. Puede ver el estado básico del pago vinculado a sus citas, pero no cobrar ni consultar montos financieros completos.

Restricciones: Sin restricciones adicionales documentadas.
