# Operaciones de seguridad para n8n

Este runbook cubre consulta e ingesta del Asistente MediFlow. Su prioridad es mantener el asistente local disponible mientras se aísla cualquier problema remoto. Ninguna operación exige exponer preguntas, respuestas, documentos, firmas o secretos.

## Contención inmediata

Ante una alerta, filtración posible, aumento anormal de costos o respuesta insegura:

- Establecer `ASSISTANT_REMOTE_ENABLED=false` en el entorno afectado y ejecutar `php artisan config:clear`.
- Desactivar en n8n los workflows de consulta e ingesta. Desactivar primero consulta si se necesita conservar la capacidad de investigar una ingesta incompleta.
- Revocar las credenciales posiblemente comprometidas: Crypto/HMAC, Supabase service role y proveedor de IA.
- No borrar de inmediato documentos ni nonces; conservar metadata mínima útil para determinar alcance, sin abrir o exportar cuerpos de ejecución.
- Verificar que el widget continúe respondiendo preguntas conocidas localmente y use el fallback seguro para preguntas desconocidas.

La desactivación remota no cambia roles, permisos ni aislamiento multi-clínica. El navegador nunca debe cambiarse para apuntar directamente a n8n.

## Rotación del secreto HMAC

Consulta e ingesta usan secretos diferentes. Rotarlos por separado reduce el alcance de una exposición.

1. Mantener `ASSISTANT_REMOTE_ENABLED=false` durante la rotación.
2. Generar un secreto criptográficamente aleatorio fuera del repositorio y de canales compartidos.
3. Cambiar la key de la credencial Crypto correspondiente en n8n.
4. Cambiar la variable local equivalente: `ASSISTANT_N8N_SECRET` para consulta o `ASSISTANT_N8N_INGEST_SECRET` para ingesta.
5. Ejecutar `php artisan config:clear`.
6. Probar una solicitud sintética con el nuevo secreto y confirmar que el secreto anterior recibe 401.
7. Repetir en cada entorno de forma independiente; no reutilizar secretos entre desarrollo y producción.
8. Registrar fecha, responsable y entorno de la rotación, nunca el valor del secreto.

El contrato firmado es `timestamp + "." + rawJsonBody`, HMAC SHA-256 hexadecimal. No cambiar serialización, orden o codificación como parte de una rotación. El nodo Webhook debe conservar Raw Body; el nodo Crypto oficial está documentado en [n8n Crypto](https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.crypto/).

## Rotación y revocación de otras credenciales

### Supabase

- Desactivar consulta e ingesta antes de rotar la service-role key.
- Rotar la clave desde Supabase, actualizar solo `MEDIFLOW_SUPABASE` en n8n y revocar la anterior.
- Confirmar que la credencial no aparece en JSON de workflow, Laravel, navegador, logs o ejecuciones guardadas.
- Probar acceso a tablas vectoriales, manifiestos y nonces con datos sintéticos.
- Revisar RLS y grants; las tablas no incluyen políticas públicas deliberadamente.

### Modelo y embeddings

- Revocar la API key comprometida desde el proveedor y actualizar la credencial privada de n8n.
- Revisar límites de gasto, actividad y permisos en la consola del proveedor.
- Si cambia el modelo de embeddings o su dimensión, crear una colección compatible y reinsertar todo el conocimiento. No mezclar embeddings de modelos o dimensiones distintas.
- Un cambio exclusivo de chat model no obliga a reindexar, pero sí a repetir prompt injection, esquema y guardrails.
- Para Gemini, hacer un smoke test de embeddings en la cuenta n8n Cloud y comprobar que la salida sea compatible con `vector(3072)` antes de la ingesta masiva. El modelo/nodo disponible puede diferir y la documentación no sustituye esa prueba.
- El nodo Gemini Chat importado expone temperatura y máximo de salida, pero no controles propios de timeout o retries. Probar timeout/error reales y confirmar el fallback; no documentarlos como configurados.

## Detener y reanudar workflows

Al detener un workflow, n8n deja de servir su Production URL. Laravel debe convertir errores/timeout en fallback sin revelar detalles.

Antes de reanudar:

- Ejecutar los validadores de JSON y sus tests.
- Verificar manualmente credenciales, tablas, funciones RPC, modelos y Data Table.
- Probar primero Test URL con una pregunta sintética.
- Activar un solo par por proyecto: base/OpenAI, OpenAI explícito, Gemini o Simple. Los workflows alternativos comparten paths de consulta e ingesta.
- Confirmar 401 para firma/timestamp inválidos, 409 para replay, 422 para payload inválido y 429 para rate limit.
- Confirmar que todas las ramas principales terminan en `Respond to Webhook`, tal como exige la configuración documentada por [n8n](https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.respondtowebhook/).

## Nonces, replay y limpieza

`assistant_request_nonces` almacena solo UUID, tiempos, tipo de workflow, rol técnico y estado; nunca pregunta, respuesta, payload, firma o identidad de usuario. La clave primaria hace atómica la detección de replay.

- Ejecutar `select public.cleanup_expired_assistant_nonces();` mediante un mecanismo administrativo seguro o una tarea programada privada.
- Programar una frecuencia coherente con la retención definida; la ventana de autenticación es de cinco minutos, pero se puede conservar metadata técnica durante un periodo corto para investigar incidentes.
- No truncar la tabla como respuesta habitual. Un truncado elimina evidencia de replay reciente y crea una ventana donde un request capturado podría reintentarse.
- Si se limpia de emergencia, mantener consulta e ingesta desactivadas hasta rotar secretos y superar la ventana de timestamps aceptados.

En la variante simple, Data Table ofrece una protección de desarrollo no atómica respecto a todos los escenarios de concurrencia y no sustituye la tabla Supabase. Solo `full_manifest=true` en el lote 0 reinicia explícitamente los cinco stores por rol; los lotes posteriores nunca deben resetearlos. Como la recarga no es transaccional y Simple Vector Store no persiste de manera apta para producción según la [documentación oficial](https://docs.n8n.io/integrations/builtin/cluster-nodes/root-nodes/n8n-nodes-langchain.vectorstoreinmemory/), un fallo intermedio obliga a repetir la carga completa en desarrollo.

## Actualizar y resincronizar conocimiento

La fuente autoritativa es `resources/assistant/knowledge-base.json`.

```powershell
npm.cmd run assistant:validate
npm.cmd run assistant:n8n-documents
npm.cmd run assistant:n8n-workflows
npm.cmd run n8n:assistant:validate
php artisan assistant:sync-n8n-knowledge --dry-run --provider=supabase --batch=10
php artisan assistant:sync-n8n-knowledge --provider=supabase --batch=10
```

Cada exportación produce documentos por entrada/rol y un checksum global. El generador de workflows vuelve a embeber el prompt y los catálogos de roles y módulos. Si cambia `knowledge-base.json`, el prompt o cualquiera de esos catálogos, validar los JSON regenerados y reimportar en n8n el par de ingesta/consulta elegido antes de sincronizar. n8n valida el formato y la consistencia del checksum firmado, pero no lo recomputa desde los documentos. La HMAC autentica el paquete procedente de Laravel.

El workflow serializa los documentos para que el Data Loader y el Vector Store conserven la metadata de cada uno. Se recomienda `--batch=10` con `ASSISTANT_N8N_INGEST_TIMEOUT_SECONDS=30`; bajar el lote si el proveedor se acerca al timeout. Aumentarlo reduce peticiones y nonces, pero alarga cada ejecución y aumenta el riesgo de que todo el lote sea rechazado.

Cada lote aceptado crea un recibo en `assistant_ingest_batches`. El trigger activa el manifiesto solo cuando están todos los índices, todos declaran `full_manifest`, coinciden checksum, versión, conteos y la suma aceptada alcanza el total. Una carga parcial o un “último lote” aislado no reemplaza la versión activa.

La activación también compara el conteo real y el conteo distinto de `document_id` del checksum en la tabla vectorial. En consulta, el manifiesto es obligatorio y se valida antes de recuperar: cualquier ausencia, versión incoherente o error de Supabase termina en fallback sin vector store ni modelo. El checksum activo se aplica en la consulta y se comprueba otra vez en el postfiltro.

Después de comprobar conteo, roles, módulos, búsqueda y respuesta:

- Confirmar que el trigger activó el checksum a partir del conjunto completo de recibos; no editar el manifiesto para saltar la comprobación.
- Probar cada rol con preguntas sintéticas.
- Mantener la colección anterior durante la ventana de rollback.
- Solo entonces llamar `public.cleanup_inactive_assistant_documents('openai', 'REEMPLAZAR_CON_CHECKSUM_INACTIVO_64_HEX')` o la variante `gemini`, después de comprobar que ese checksum no es activo ni necesario para rollback.

No borrar conocimiento válido antes de que la versión nueva esté completa, verificada y activa.

## Rollback documental

Si la versión nueva produce fallbacks, mezcla módulos o resultados incorrectos:

- Desactivar remoto o detener consulta para contener el riesgo.
- Restaurar `active_checksum` del proveedor al checksum anterior verificado en `assistant_knowledge_manifests` mediante un cambio administrativo controlado.
- No llamar `cleanup_inactive_assistant_documents(provider, checksum)` para ninguna versión candidata a rollback.
- Repetir consultas sintéticas por rol y módulo.
- Reactivar consulta con `ASSISTANT_REMOTE_ENABLED=false`; habilitar Laravel solo tras aprobar la verificación.

Si la colección anterior ya fue eliminada, regenerar el paquete desde la revisión válida de `knowledge-base.json` y sincronizarlo como nueva versión. No insertar documentos manuales como parche porque romperían la trazabilidad del checksum.

## Fallbacks excesivos y control de costos

Investigar un aumento de `fallback_used=true` usando únicamente metadata segura:

- Diferenciar `INVALID_REMOTE_RESPONSE`, falta de contexto, timeout, error de credencial, rate limit y modelo no disponible.
- Revisar por `workflow version`, `role`, `module`, `status`, latencia y cantidad de documentos recuperados.
- Confirmar que el manifiesto activo corresponde al proveedor y que tabla, función RPC y modelo de embeddings están alineados.
- Confirmar que el umbral de similitud y Top K no se cambiaron accidentalmente.
- No bajar el umbral ni retirar filtros de rol para “mejorar” respuestas.
- No habilitar reintentos múltiples del modelo; primero identificar la causa.

Los límites complementarios son: pregunta máxima, hasta 30 candidatos de recuperación, Top K final entre 3 y 10, contexto máximo, una llamada al modelo, rate limit Laravel y control por workflow/rol en Supabase. El conjunto candidato se filtra estrictamente por rol, módulo, locale, estado y checksum antes de reducirse al Top K que recibe el modelo.

## Retención y observabilidad

Conservar solo:

- `request_id`, timestamp y versión del workflow.
- rol, módulo, estado y latencia.
- cantidad de documentos recuperados.
- confianza, uso de fallback y código de error seguro.

No conservar:

- pregunta, respuesta o documentos.
- cuerpo, cabeceras completas, firma o secreto.
- nombres, correos, IP innecesaria o identificadores de usuario/clínica.
- datos médicos, financieros o formularios.

Los workflows construyen únicamente esa telemetría técnica, pero no la persisten en esta fase. `saveDataSuccessExecution` y `saveDataErrorExecution` están configurados en `none`, el guardado manual está desactivado y no existe un sink de observabilidad. Esta limitación es deliberada para evitar retener preguntas, respuestas o contexto. Si una fase posterior agrega persistencia, debe usar una tabla o sink privado con allowlist estricta de los campos anteriores, retención corta y ninguna copia del contenido.

No habilitar el guardado completo de ejecuciones para obtener métricas. Revisar los controles de la instancia porque cambian según versión y plan, y no añadir nodos de logging que serialicen `$json`, prompts, cuerpos o resultados completos.

## Respuesta ante filtración

### Secreto HMAC de consulta

- Deshabilitar remoto, detener consulta, rotar el secreto en n8n/Laravel y esperar a que expire la ventana temporal.
- Limpiar solo nonces vencidos; conservar metadata reciente para detectar replay.
- Revisar solicitudes por request ID, estado y rol, no por contenido.

### Secreto HMAC de ingesta

- Detener ingesta inmediatamente y rotar su secreto.
- Comprobar manifiestos y checksums; no activar ni limpiar colecciones nuevas hasta verificar su procedencia.
- Si apareció un checksum no autorizado, restaurar el anterior y revocar credenciales relacionadas.

### Service-role key de Supabase

- Detener ambos workflows, rotar/revocar la clave y revisar actividad de tablas/RPC.
- Restaurar desde respaldo si hubo cambios no autorizados.
- Considerar comprometidos nonces, manifiestos y documentos del proyecto, pero no asumir exposición de datos clínicos: esos datos nunca deben existir en este almacén.

### Credencial de IA

- Revocar la key, revisar consumo y restringir permisos/cuotas.
- No enviar historiales o contenido de ejecuciones al soporte del proveedor.
- Repetir las pruebas de salida y prompt injection con la credencial nueva.

## Checklist de reapertura

- [ ] Incidente contenido y remoto deshabilitado.
- [ ] Credenciales afectadas revocadas y rotadas.
- [ ] Workflows validados e importados sin secretos ni IDs reales.
- [ ] Solo un par de workflows activo para los paths compartidos.
- [ ] HMAC, timestamp, request ID y replay probados.
- [ ] Manifiesto fail-closed y filtros de rol, módulo, locale, estado y checksum confirmados.
- [ ] Manifiesto y dimensiones de embeddings verificados.
- [ ] Contrato remoto exacto con `answer`, `confidence`, `steps`, `suggestions` y `can_escalate`, sin campos ausentes ni adicionales, y validación final probados.
- [ ] Retención revisada y observabilidad persistente pendiente reconocida sin habilitar contenido.
- [ ] Fallback local verificado.
- [ ] Prueba integral sintética aprobada antes de reactivar remoto.

## Registro del incidente

Documentar cronología, entorno, tipo de credencial, request IDs afectados, códigos seguros, acciones, responsable y cierre. No adjuntar secretos, URLs completas, cuerpos, prompts, preguntas, respuestas, pacientes, pagos ni documentos RAG.
