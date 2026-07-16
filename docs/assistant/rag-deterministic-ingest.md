# Ingesta RAG determinista

Este documento define la corrección implementada después de la auditoría forense de la ingesta. No sustituye la arquitectura local-first del asistente ni modifica los workflows de consulta.

## Causa corregida

`Check Ingest Result` ya no intenta recuperar ejecuciones anteriores mediante `$('Classify ...').all()`. Cada iteración devuelve un único item a `Loop Over Documents`; la salida `done` entrega esos items acumulados y el check consume únicamente:

```js
const items = $input.all();
```

Con `batchSize: 1`, diez documentos confirmados producen diez items en `done`.

## Contexto por item

Después de `Convert Documents`, cada item lleva `request` y `document`. `request.provider` identifica el espacio de embeddings (`gemini` u `openai`); el `provider: supabase` del payload Laravel sigue identificando el backend firmado y se valida antes del loop.

Además de los nueve campos funcionales del contrato, cada `request` incluye un único `expected_document_id`. Es una extensión interna mínima y deliberada: permite que el check detecte un resultado ajeno usando solo `done`, sin copiar la lista completa del lote en cada item. Su costo total es O(n) y no contiene texto, embeddings ni datos de usuarios o clínicas. Los nueve campos comunes deben ser idénticos en todos los items; `expected_document_id` cambia por documento.

Cada iteración confirmada termina exactamente así:

```json
{
  "request": {
    "request_id": "uuid",
    "provider": "gemini",
    "checksum": "sha256",
    "knowledge_version": 2,
    "batch_index": 0,
    "batch_count": 17,
    "document_count": 161,
    "batch_document_count": 10,
    "full_manifest": true,
    "expected_document_id": "document-1"
  },
  "result": {
    "document_id": "document-1",
    "checksum": "sha256",
    "storage_status": "inserted",
    "confirmed": true
  }
}
```

Los únicos estados confirmables son `inserted` y `already_present`. Un fallo genera `failed` con `confirmed: false` y termina en la respuesta segura; nunca vuelve al loop como confirmado.

## Escritura explícita

La escritura de las variantes persistentes ya no usa `vectorStoreSupabase` en modo `insert`. Los nodos de consulta conservan ese componente para recuperación RAG.

Flujo de un documento persistente:

1. Consultar `document_id` y `knowledge_checksum`.
2. Si existe, construir `already_present`; no generar embedding ni llamar la RPC.
3. Si no existe, solicitar el embedding mediante HTTP Request con credencial predefinida.
4. Validar el vector antes de cualquier escritura.
5. Llamar la RPC Supabase idempotente.
6. Validar el contrato de la RPC.
7. Volver a consultar la tabla y construir el resultado confirmado.

Los workflows persistentes versionan un único host Supabase deliberadamente ficticio (`your-project.supabase.co`) dentro del nodo **Ingest Endpoint Configuration**. Ese nodo detiene la ejecución con `MEDIFLOW_SUPABASE_BASE_URL_UNCONFIGURED` mientras conserve el marcador o tenga un host vacío/inválido.
Al importar, mantener el workflow inactivo, abrir solo ese nodo, reemplazar el marcador por la URL base HTTPS del proyecto autorizado (sin ruta ni clave) y luego seleccionar las credenciales predefinidas de Supabase y embeddings. No hay IDs de credenciales, URLs privadas ni secretos en JSON.
No publicar hasta que el validador haya confirmado la configuración; los nodos HTTP reutilizan esa única URL base y nunca incrustan la service-role key.

## Embeddings

| Proveedor | Modelo | Dimensión | Credencial n8n |
| --- | --- | ---: | --- |
| Gemini | `gemini-embedding-001` | 3072 | `googlePalmApi` |
| OpenAI | `text-embedding-3-small` | 1536 | `openAiApi` |

El validador exige que el embedding sea un array con la longitud exacta y que cada elemento sea un `number` finito. Rechaza `null`, strings, `NaN`, `Infinity` y dimensiones distintas.

## RPC implementadas

La fuente SQL `n8n/supabase/assistant-rag-schema.sql` ya contiene:

- `upsert_assistant_document_gemini(content text, metadata jsonb, embedding jsonb)`;
- `upsert_assistant_document_openai(content text, metadata jsonb, embedding jsonb)`.

Ambas delegan en una implementación privada que:

- valida contenido, identidad, checksum, tipo y dimensión del vector;
- obtiene un advisory transaction lock por proveedor, ID y checksum;
- responde `already_present` sin insertar duplicados;
- inserta en la tabla dimensional correcta;
- confirma la fila dentro de la misma transacción;
- devuelve únicamente `document_id`, `checksum`, `storage_status` y `confirmed`.

Las funciones están revocadas para `public`, `anon` y `authenticated`; solo los wrappers se conceden a `service_role`. La Fase 3 no debe volver a crear estas RPC: debe revisar y desplegar esta fuente SQL en el proyecto privado.

## Respuesta de ingesta

Todas las ramas del workflow responden exactamente siete campos:

```json
{
  "ok": true,
  "request_id": "uuid",
  "accepted": 10,
  "rejected": 0,
  "checksum": "sha256",
  "knowledge_version": 2,
  "activated": false
}
```

No se devuelven contenido, metadata, documentos, embeddings ni detalles internos de error.

## Recuperación de los diez documentos existentes

Con diez documentos ya almacenados, cero recibos y el manifiesto inactivo, el primer lote reintentado toma diez veces la rama existente. El contador de embeddings y RPC permanece en cero, `done` contiene diez resultados `already_present`, y el check devuelve `accepted: 10`, `rejected: 0`.

## Referencia operativa final

1. Aplicar de forma controlada el SQL actualizado en Supabase; no usar scripts de reparación sobre la tabla no vacía.
2. Configurar una sola vez el host autorizado en `Ingest Endpoint Configuration` de cada workflow persistente importado.
3. Seleccionar las credenciales predefinidas de Gemini/OpenAI y Supabase en n8n.
4. Importar los workflows inactivos y ejecutar un smoke test con datos sintéticos.
5. Verificar el reintento de los diez documentos existentes y la creación del recibo sin duplicados.
6. Activar el workflow únicamente después de comprobar HMAC, anti-replay, RLS y respuestas de siete campos.

Las pruebas automatizadas de esta fase usan mocks y funciones puras; no realizan llamadas a n8n, Gemini, OpenAI ni Supabase.
