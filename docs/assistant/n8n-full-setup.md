# Configuración completa de n8n para el Asistente MediFlow

Esta guía instala la Fase 4 sin cambiar el principio **local-first**: el widget resuelve primero con `knowledge-base.json`; Laravel llama a n8n únicamente ante una pregunta desconocida, con conexión `ONLINE` y asistencia remota habilitada. El navegador nunca conoce las URLs de n8n, las credenciales de Supabase ni los secretos HMAC.

La variante recomendada para producción es Supabase/pgvector. Simple Vector Store es una ayuda de desarrollo no persistente y no debe usarse como repositorio de producción. Las referencias oficiales pertinentes son: [Webhook y Raw Body](https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.webhook/), [Respond to Webhook](https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.respondtowebhook/), [Crypto](https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.crypto/), [Supabase Vector Store](https://docs.n8n.io/integrations/builtin/cluster-nodes/root-nodes/n8n-nodes-langchain.vectorstoresupabase/), [Simple Vector Store](https://docs.n8n.io/integrations/builtin/cluster-nodes/root-nodes/n8n-nodes-langchain.vectorstoreinmemory/), [Data Table](https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.datatable/), [Basic LLM Chain](https://docs.n8n.io/integrations/builtin/cluster-nodes/root-nodes/n8n-nodes-langchain.chainllm/) y [Structured Output Parser](https://docs.n8n.io/integrations/builtin/cluster-nodes/sub-nodes/n8n-nodes-langchain.outputparserstructured/).

## Preparación

- Trabajar primero en un proyecto n8n y Supabase de pruebas, sin datos clínicos ni financieros.
- Tener acceso al SQL Editor de Supabase y permiso para crear credenciales y workflows en n8n Cloud.
- Mantener `ASSISTANT_REMOTE_ENABLED=false` hasta terminar todas las verificaciones.
- No copiar `.env`, claves API, service-role keys ni secretos HMAC al repositorio, capturas, tickets o logs.
- No activar guardado de preguntas, respuestas, cuerpos o documentos en la observabilidad del workflow.

## Instalación en exactamente 25 pasos

1. **Elegir el proveedor de IA.** Seleccionar OpenAI o Gemini según las credenciales y modelos disponibles en la cuenta de n8n. No asumir que una suscripción de interfaz incluye API. Para OpenAI, la variante preparada usa `text-embedding-3-small` a 1.536 dimensiones; para Gemini, el SQL prepara `gemini-embedding-001` a 3.072 dimensiones por defecto. Esa dimensión de Gemini debe confirmarse con un smoke test real en n8n Cloud antes de una ingesta masiva. La tabla Gemini conserva `vector(3072)` y, para este corpus pequeño, usa búsqueda exacta sin HNSW: el índice HNSW de pgvector sobre `vector` no admite 3.072 dimensiones. Si el nodo/modelo disponible produce otra dimensión, adaptar tabla y RPC y reinsertar todos los documentos. Consultar las guías oficiales de [embeddings OpenAI](https://developers.openai.com/api/docs/guides/embeddings) y [embeddings Gemini](https://ai.google.dev/gemini-api/docs/embeddings).

2. **Crear el almacén persistente.** Crear un proyecto privado de Supabase para RAG. Usar la service-role key solo como credencial servidor-a-servidor dentro de n8n; nunca en Laravel público, Blade, JavaScript o el navegador. Simple Vector Store se reserva para una prueba local/demo.

3. **Ejecutar el esquema SQL.** Abrir el SQL Editor de Supabase, revisar y ejecutar `n8n/supabase/assistant-rag-schema.sql`. El script crea tablas vectoriales separadas, funciones de similitud, manifiestos, nonces, `assistant_ingest_batches`, índices compatibles y funciones de limpieza. OpenAI usa HNSW sobre 1.536 dimensiones; Gemini conserva `vector(3072)` con búsqueda exacta, apropiada para el corpus actual, sin crear un índice HNSW incompatible. El trigger de recibos activa un checksum solo cuando existe el conjunto completo y consistente de lotes `full_manifest` y el conteo real y distinto de documentos vectoriales coincide con el manifiesto. No ejecutar solo fragmentos sin comprobar dependencias.

4. **Importar el workflow de ingesta.** En n8n usar **Import from File** con una sola variante: `mediflow-assistant-ingest-supabase-openai.json`, `mediflow-assistant-ingest-supabase-gemini.json`, el alias base OpenAI `mediflow-assistant-ingest-supabase.json`, o `mediflow-assistant-ingest-simple.json` para desarrollo. Todos comparten el path `mediflow-assistant-ingest`; no importar/activar dos variantes simultáneamente en el mismo proyecto.

5. **Importar el workflow de consulta.** Importar la consulta correspondiente al mismo par: `mediflow-assistant-query-supabase-openai.json`, `mediflow-assistant-query-supabase-gemini.json`, el alias base OpenAI `mediflow-assistant-query-supabase.json`, o `mediflow-assistant-query-simple.json`. Todos comparten `mediflow-assistant-query`; activar solo el par elegido. La variante simple debe conservar la nota “Solo desarrollo: almacenamiento no persistente”.

6. **Crear las credenciales Crypto.** Crear dos credenciales de tipo Crypto con claves aleatorias robustas y diferentes: una para consulta (`MEDIFLOW_HMAC_SECRET`) y otra para ingesta (`MEDIFLOW_HMAC_INGEST_SECRET`). El valor se configura solo en n8n y en el `.env` local correspondiente; nunca dentro del JSON del workflow.

7. **Crear la credencial de Supabase.** Crear `MEDIFLOW_SUPABASE` con la URL del proyecto y la service-role key. Revisar que la credencial quede accesible únicamente al proyecto de n8n autorizado. No usar la anon key para esta comunicación privada.

8. **Crear la credencial del modelo.** Crear o seleccionar `MEDIFLOW_AI_MODEL` para el proveedor elegido. En el nodo de chat seleccionar manualmente un modelo disponible, temperatura entre 0 y 0,2, límite de salida razonable y sin herramientas, memoria ni acceso HTTP. El nodo Gemini Chat importado no expone controles propios de timeout/reintentos; no fingir que están configurados y comprobar su fallo real contra Test URL y el fallback de Laravel.

9. **Crear la credencial de embeddings.** Crear o seleccionar `MEDIFLOW_EMBEDDINGS`. Mantener el mismo proveedor, modelo y dimensión durante ingesta y consulta. Antes de la carga completa ejecutar un documento sintético y confirmar en n8n Cloud que la dimensión coincide con `vector(1536)` o `vector(3072)`; nunca mezclar espacios vectoriales.

10. **Asignar manualmente todas las credenciales.** Abrir cada nodo marcado como placeholder y seleccionar Crypto, Supabase, chat y embeddings. En la variante simple, crear/seleccionar la Data Table de nonces. Sus cinco stores usan claves exclusivas por rol; el reset explícito de los cinco ocurre solo con `full_manifest=true` y `batch_index=0`, por lo que sigue siendo una variante no transaccional de desarrollo.

11. **Guardar los workflows sin activarlos.** Verificar que los Webhook usen POST, `Raw Body` esté activado y la respuesta se entregue mediante `Respond to Webhook`. Confirmar que solo exista un workflow de ingesta y uno de consulta para los paths compartidos. No guardar webhook IDs, URLs privadas ni IDs reales de credenciales en archivos versionados.

12. **Obtener las Test URLs.** Desde cada Webhook copiar temporalmente la Test URL de ingesta y consulta. Escuchar primero en modo test. No confundir Test URL con Production URL ni publicar esas direcciones.

13. **Configurar el `.env` local.** Añadir localmente las Test URLs y los mismos secretos de Crypto en `ASSISTANT_N8N_WEBHOOK_URL`, `ASSISTANT_N8N_SECRET`, `ASSISTANT_N8N_INGEST_WEBHOOK_URL` y `ASSISTANT_N8N_INGEST_SECRET`. Ajustar `ASSISTANT_N8N_INGEST_TIMEOUT_SECONDS=30` si corresponde. Conservar `ASSISTANT_REMOTE_ENABLED=false` y no versionar ni subir `.env`.

14. **Limpiar la caché de configuración.** Ejecutar `php artisan config:clear` y comprobar que Laravel no muestre ni registre los valores. No usar `config:cache` mientras se estén cambiando URLs de prueba.

15. **Generar documentos y workflows.** Ejecutar `npm.cmd run assistant:n8n-documents` y `npm.cmd run assistant:n8n-workflows`. El paquete `n8n/knowledge/assistant-documents.json` se deriva únicamente de `resources/assistant/knowledge-base.json`, expande una entrada por cada rol permitido e incluye checksum global. El segundo comando vuelve a embeber el prompt y los catálogos de roles/módulos en los JSON. Siempre que cambien la base de conocimiento, el prompt o esos catálogos, validar los workflows regenerados y reimportar en n8n el par elegido antes de sincronizar o activar.

16. **Ejecutar el dry-run de ingesta.** Ejecutar `php artisan assistant:sync-n8n-knowledge --dry-run --provider=supabase --batch=10`. Revisar número de documentos, lotes, versión y checksum; el comando no debe imprimir contenido documental ni secretos. El workflow serializa documentos uno a uno para conservar metadata segura, así que 10 es el lote recomendado.

17. **Sincronizar el conocimiento.** Poner el webhook de ingesta en escucha y ejecutar `php artisan assistant:sync-n8n-knowledge --provider=supabase --batch=10`. Mantener `ASSISTANT_N8N_INGEST_TIMEOUT_SECONDS=30`; si la cuenta/modelo excede ese tiempo, bajar el lote antes de ampliar límites. Más documentos por petición reducen webhooks pero elevan latencia y riesgo de timeout. Cada sincronización envía un `full_manifest` firmado; `--force` regenera antes el paquete desde la fuente autoritativa. En Supabase la activación ocurre solo al completar todos los recibos consistentes. `--provider=simple` es solo desarrollo.

18. **Probar el webhook de consulta.** Poner el webhook de consulta en escucha y enviar únicamente una solicitud sintética firmada desde Laravel. Confirmar HTTP 200 con exactamente los cinco campos obligatorios `answer`, `confidence`, `steps`, `suggestions` y `can_escalate`, sin campos adicionales ni ausentes; comprobar también 401, 409 y 422 con los casos de seguridad documentados.

19. **Ejecutar la prueba integral de Laravel.** Usar `php artisan assistant:test-n8n --role=medico --module=prescriptions --question="¿Cómo puedo crear una receta médica?" --show-metadata`. Agregar `--expect-remote` cuando se espere una respuesta RAG válida. No usar nombres, cédulas, diagnósticos, pagos ni preguntas reales de pacientes.

20. **Confirmar aislamiento por rol.** Probar `administrador`, `recepcionista`, `caja_finanzas`, `medico` y `super_admin` con preguntas sintéticas. Verificar en cada ejecución que los documentos recuperados tengan el mismo `role`, `status=active`, `locale` solicitado y módulo permitido; una consulta cruzada debe responder de forma segura sin llamar al modelo cuando corresponda.

21. **Activar los workflows en n8n.** Solo después de validar HMAC, timestamp, antirreplay, rate limit, filtros, parser y guardrails, activar exactamente un par compatible: base/OpenAI, OpenAI explícito, Gemini o Simple de desarrollo. Los paths se comparten y dos pares activos provocarían conflicto. Mantener la ingesta limitada a operadores autorizados.

22. **Cambiar a Production URLs.** Copiar las Production URLs activas a las variables locales de consulta e ingesta, ejecutar otra vez `php artisan config:clear` y repetir dry-run y prueba sintética. No pegar la URL completa en documentación o logs si incorpora información reservada.

23. **Mantener el remoto deshabilitado.** Dejar `ASSISTANT_REMOTE_ENABLED=false` mientras se ejecutan los tests Laravel, validadores de workflows, build, E2E y pruebas negativas de seguridad. El asistente local debe continuar funcionando sin n8n.

24. **Hacer la prueba integral previa.** Ejecutar la batería final, revisar la retención de ejecuciones n8n, confirmar que ningún registro guarda pregunta/respuesta/cuerpo/cabeceras, comprobar fallback con modelo desconectado y verificar rollback. Los workflows construyen telemetría técnica permitida, pero su persistencia queda deliberadamente pendiente: `saveDataSuccessExecution` y `saveDataErrorExecution` están en `none` y no existe un sink de observabilidad. No habilitar guardado indiscriminado para suplirlo ni registrar contenido.

25. **Activar Laravel solo después de aprobar la revisión.** Cambiar localmente a `ASSISTANT_REMOTE_ENABLED=true` y `ASSISTANT_PROVIDER=n8n`, ejecutar `php artisan config:clear` y repetir una pregunta sintética desconocida desde el widget. Si algo falla, volver inmediatamente a `ASSISTANT_REMOTE_ENABLED=false`; el flujo local-first permanece disponible.

## Campos que deben completarse manualmente

| Lugar | Campo manual | OpenAI | Gemini |
|---|---|---|---|
| Crypto de consulta | Key de `MEDIFLOW_HMAC_SECRET` | Secreto local de consulta | Secreto local de consulta |
| Crypto de ingesta | Key de `MEDIFLOW_HMAC_INGEST_SECRET` | Secreto local de ingesta | Secreto local de ingesta |
| Supabase | Project URL y service-role key | Credencial privada | Credencial privada |
| Vector Store | Table name | `assistant_documents_openai_1536` | `assistant_documents_gemini_3072` |
| Vector Store | Query/RPC name | `match_assistant_documents_openai` con HNSW | `match_assistant_documents_gemini` con búsqueda exacta, sin HNSW |
| Chat model | Credencial y modelo disponible | Nodo OpenAI Chat Model | Nodo Google Gemini Chat Model; sin timeout/retries configurables en el nodo importado |
| Embeddings | Credencial, modelo y smoke test | `text-embedding-3-small`, 1536 | `gemini-embedding-001`, 3072 solo después de verificar en Cloud |
| Webhook de consulta | Test/Production URL | `ASSISTANT_N8N_WEBHOOK_URL` | `ASSISTANT_N8N_WEBHOOK_URL` |
| Webhook de ingesta | Test/Production URL | `ASSISTANT_N8N_INGEST_WEBHOOK_URL` | `ASSISTANT_N8N_INGEST_WEBHOOK_URL` |
| Data Table, solo simple | Tabla de nonces | Recurso de desarrollo | Recurso de desarrollo |
| Simple Vector Store | Memory key exclusiva | Solo desarrollo | Solo desarrollo |

No editar el JSON exportado para introducir valores reales. La selección de credenciales se hace en la interfaz de n8n después de importar. Si una versión de n8n solicita volver a seleccionar una tabla, función RPC, Data Table o modelo, hacerlo manualmente y volver a ejecutar las pruebas de seguridad.

## Contratos que deben permanecer alineados

### Consulta

Laravel serializa una sola vez un JSON UTF-8 compacto, sin escapar barras ni Unicode, con este orden: `request_id`, `question`, `role`, `module`, `route`, `connection_state`, `locale`, `knowledge_version`, `timestamp`. Firma los bytes exactos:

```text
hex(HMAC-SHA256(secret, X-MediFlow-Timestamp + "." + rawJsonBody))
```

n8n debe leer el cuerpo crudo, comprobar que los timestamps y request IDs de cabecera/cuerpo coincidan, validar la ventana de 300 segundos y la tolerancia futura de 30 segundos, y comparar la firma hexadecimal sin coerción de tipos. El valor de `X-MediFlow-Assistant-Version` representa la versión de conocimiento enviada por Laravel; no es una versión de n8n ni del modelo.

El nonce se inserta atómicamente en `assistant_request_nonces`. Un UUID repetido responde 409 y no alcanza el vector store ni el modelo. El trigger SQL añade un segundo límite de 60 nonces por workflow, rol y minuto; Laravel conserva su propio límite anterior.

### RAG y aislamiento

La búsqueda persistente es *fail-closed* respecto del manifiesto: antes de consultar el vector store exige un manifiesto activo válido, con proveedor, versión, checksum hexadecimal y conteo positivo. Si falta, está incompleto o Supabase falla, se responde con fallback y no se ejecutan recuperación ni modelo. El vector store filtra por `role`, `status=active`, `locale` y checksum activo; después el postfiltro vuelve a exigir el mismo rol, estado, locale, checksum y módulo antes de construir contexto.

La recuperación obtiene como máximo 30 candidatos para evitar que el filtro posterior pierda documentación pertinente. Después de aplicar umbral y aislamiento se ordenan por similitud y se reduce al Top K final configurable entre 3 y 10, con 5 por defecto. La lista de módulos generales permitidos no autoriza a mezclar roles. Si no hay documentos suficientes sobre el módulo solicitado o no se supera el umbral, no se llama al modelo y se devuelve el fallback exacto.

Cada consulta es independiente: no hay memoria remota, herramientas, acceso a bases clínicas, búsquedas web ni llamadas HTTP del modelo. El Structured Output Parser y Laravel aceptan exactamente cinco campos obligatorios: `answer`, `confidence`, `steps`, `suggestions` y `can_escalate`. Una validación independiente descarta campos adicionales o ausentes, HTML, scripts, URLs externas, comandos, SQL e instrucciones para eludir permisos.

### Ingesta y activación segura

La ingesta usa secreto y URL separados. Los lotes llevan `request_id`, proveedor, índice y cantidad de lotes, `full_manifest`, checksum, versión, total documental, documentos y timestamp. Cada documento se inserta serialmente para conservar su metadata sin cruces; un lote de 10 mantiene un equilibrio razonable con el timeout Laravel de 30 segundos, y debe reducirse si el smoke test real es más lento.

Tras confirmar una ingesta de lote, el workflow persiste un recibo en `assistant_ingest_batches`. El trigger activa el manifiesto únicamente cuando todos los índices esperados están presentes, cada recibo es `full_manifest`, los conteos/versiones coinciden y la suma aceptada equivale al total. No depende solo del último lote. Una carga parcial conserva el checksum activo anterior.

El checksum se calcula en el exportador de MediFlow y n8n lo usa como namespace firmado y criterio de consistencia; el workflow no vuelve a calcularlo a partir del contenido. La seguridad descansa también en HMAC, esquema estricto y origen Laravel. No borrar la versión anterior antes de verificar la nueva. Para limpiar, pasar alcance explícito: `cleanup_inactive_assistant_documents(provider, checksum_inactivo)`; la función impide borrar el checksum activo.

En Simple Vector Store no hay manifiesto transaccional: solo `full_manifest=true` con lote 0 ejecuta el reset explícito de los cinco stores (`administrador`, `caja_finanzas`, `medico`, `recepcionista`, `super_admin`). Después se insertan los documentos serialmente. Un fallo posterior puede dejar estado parcial, por lo que esta variante sigue prohibida en producción.

## Comandos de verificación

```powershell
php artisan route:list
php artisan test
npm.cmd run assistant:validate
npm.cmd run assistant:validate:test
npm.cmd run assistant:manuals
npm.cmd run assistant:n8n-documents
npm.cmd run assistant:n8n-workflows
npm.cmd run n8n:assistant:validate
npm.cmd run n8n:assistant:validate:test
npm.cmd run build
npm.cmd run test:e2e
git diff --check
git status
```

Los tests y validadores usan fixtures o `Http::fake()` y no deben llamar a n8n, Supabase, OpenAI o Gemini reales. La importación y selección de recursos en una cuenta concreta de n8n Cloud sigue siendo una verificación manual: un JSON estructuralmente válido no demuestra por sí solo que las credenciales, tablas o modelos de esa cuenta estén disponibles.
