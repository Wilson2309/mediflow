# Manual del Asistente MediFlow — Súper Admin

> Archivo generado. No editar manualmente: su fuente es `resources/assistant/knowledge-base.json`.

Entradas autorizadas para el rol `super_admin`.
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

## Panel global

- Módulo: Clínicas globales
- Pregunta: ¿Cómo accedo al panel global?
- Requiere conexión: Sí
- Ruta relacionada: super-admin.clinics.index

El rol super_admin accede al listado global de clínicas. Su único permiso operativo es super_admin.access y no incluye módulos clínicos o financieros de una clínica.

Restricciones: Sin restricciones adicionales documentadas.

## Crear clínica y administrador principal

- Módulo: Clínicas globales, Onboarding SaaS
- Pregunta: ¿Cómo creo una clínica y su administrador principal?
- Requiere conexión: Sí
- Ruta relacionada: super-admin.clinics.create

Nueva clínica crea la organización y, en la misma transacción, un usuario administrador asociado y activo. Debes completar nombre, estado y credenciales del administrador; los demás datos son opcionales según el formulario.

Pasos:

1. Abre Clínicas.
2. Pulsa Nueva clínica.
3. Completa datos generales, suscripción y estado.
4. Completa nombre, correo y contraseña del administrador.
5. Revisa y guarda.

Restricciones: La creación global se bloquea sin conexión.

## Onboarding SaaS

- Módulo: Onboarding SaaS
- Pregunta: ¿Cómo funciona el onboarding de una clínica?
- Requiere conexión: Sí
- Ruta relacionada: super-admin.clinics.create

El onboarding implementado consiste en registrar datos de la clínica, estado, datos de suscripción y crear su administrador principal. No existe un asistente de pasos posterior separado.

Pasos:

1. Completa el formulario Nueva clínica.
2. Revisa administrador, plan, fecha de corte y estado.
3. Guarda para crear ambos registros.

Restricciones: Sin restricciones adicionales documentadas.

## Plan y fecha de corte

- Módulo: Suscripciones
- Pregunta: ¿Cómo configuro el plan de suscripción y la fecha de corte?
- Requiere conexión: Sí
- Ruta relacionada: super-admin.clinics.index

En crear o editar clínica puedes indicar subscription_plan y subscription_end_date. Si el plan queda vacío, MediFlow guarda basic. No hay catálogo global de planes ni cobro automático implementado.

Restricciones: Sin restricciones adicionales documentadas.

## Notas internas

- Módulo: Clínicas globales
- Pregunta: ¿Dónde registro notas internas de una clínica?
- Requiere conexión: Sí
- Ruta relacionada: Ninguna

El formulario global de clínica incluye internal_notes. Son notas administrativas internas; evita incluir datos clínicos, identificaciones o credenciales.

Restricciones: Sin restricciones adicionales documentadas.

## Activar o inactivar clínica

- Módulo: Clínicas globales
- Pregunta: ¿Cómo activo o inactivo una clínica?
- Requiere conexión: Sí
- Ruta relacionada: super-admin.clinics.index

Abre Editar clínica y cambia el estado entre active e inactive. Una clínica inactiva queda fuera de selectores y sus rutas privadas por clínica responden 403.

Pasos:

1. Abre el listado global.
2. Entra en Editar.
3. Revisa la clínica.
4. Cambia el estado y guarda.

Restricciones: El cambio global se bloquea sin conexión.

## SuperAdmin y administrador de clínica

- Módulo: Clínicas globales, Permisos
- Pregunta: ¿Cuál es la diferencia entre SuperAdmin y administrador de clínica?
- Requiere conexión: No
- Ruta relacionada: Ninguna

SuperAdmin gestiona organizaciones, estado, datos de suscripción y alta del administrador principal. El administrador de clínica gestiona la operación de sus clínicas. SuperAdmin no recibe permisos clínicos o financieros por su rol global.

Restricciones: Sin restricciones adicionales documentadas.

## Panel global sin conexión

- Módulo: Clínicas globales, Suscripciones, Onboarding SaaS, Conexión y borradores
- Pregunta: ¿Qué acciones de SuperAdmin se bloquean sin conexión?
- Requiere conexión: No
- Ruta relacionada: Ninguna

Crear o editar clínicas, cambiar estado, configurar suscripción y completar onboarding requieren conexión. El asistente solo explica estas funciones y no ejecuta cambios.

Restricciones: Sin restricciones adicionales documentadas.
