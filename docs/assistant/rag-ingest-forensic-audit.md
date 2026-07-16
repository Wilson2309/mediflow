# Auditoria forense de la ingesta RAG

Fecha: 2026-07-13. Alcance: ingesta Laravel -> n8n -> Gemini -> Supabase. Esta auditoria usa solo archivos locales, `Http::fake()` y ejecucion local del codigo de los nodos. No llama a n8n, Gemini ni Supabase y no lee archivos `.env`.

## Contrato Laravel

`N8nKnowledgeSyncService` parte el paquete documental en lotes. Para cada lote crea un `request_id` UUID y envia un JSON estable con:

```json
{
  "request_id": "uuid",
  "provider": "supabase",
  "batch_index": 0,
  "batch_count": 17,
  "full_manifest": true,
  "checksum": "sha256",
  "knowledge_version": 2,
  "document_count": 161,
  "documents": [{ "document_id": "...", "content": "...", "metadata": {} }],
  "timestamp": "ISO-8601 UTC"
}
```

`AssistantHmacSigner` serializa con `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` y firma `timestamp + '.' + rawBody` usando HMAC SHA-256. Laravel envia `Content-Type: application/json`, `Accept: application/json`, `X-MediFlow-Request-Id`, `X-MediFlow-Timestamp`, `X-MediFlow-Signature` y `X-MediFlow-Assistant-Version`.

La respuesta exitosa debe contener exactamente siete campos, sin adicionales: `ok`, `request_id`, `accepted`, `rejected`, `checksum`, `knowledge_version`, `activated`. Laravel exige `ok: true`, `accepted` igual al tamano del lote, `rejected: 0`, los tipos JSON nativos correctos y `activated: false` salvo en el ultimo lote, donde debe ser `true`.

## Flujo auditado antes de la correccion de Fase 2

1. Webhook, validacion de payload, HMAC y nonce conservan el sobre `payload` con request, checksum, version y lotes.
2. `Convert Documents` agrega `metadata.document_id` y `metadata.checksum`; conserva contenido y metadata autorizada.
3. `Loop Over Documents` procesa un item por ejecucion.
4. `Check Existing Document` busca por `document_id` y `knowledge_checksum`. `Classify Existing Document` produce evidencia `document_id`, `checksum`, `storage_status` y `confirmed`.
5. TRUE de `Document Is Already Present` vuelve al loop como `already_present`; FALSE llega a `Supabase Vector Store Insert`, despues a `Confirm Stored Document`, `Classify Stored Document` y `Document Insert Confirmed`.
6. La salida `done` del loop llega a `Check Ingest Result`, despues a recibo, comprobacion de recibo y, solo en ultimo lote, comprobacion de manifiesto.

Los inserts usan `assistant_documents_gemini_3072` con metadata `jsonb` y embedding `vector(3072)`. El trigger de deduplicacion evita repetir la identidad `(document_id, checksum)`. El recibo se crea solamente si `ingest_ok` es verdadero. El trigger SQL de recibos activa el manifiesto solo si existen todos los lotes consistentes y todos los documentos esperados.

## Causa raiz demostrada

El problema esta en `Check Ingest Result` dentro de `scripts/lib/n8n-assistant-workflows.mjs`. Su agregador usa:

```js
collect('Classify Existing Document')
collect('Classify Stored Document')
```

sin especificar `runIndex`. n8n define `$("node").all()` como los items de la ejecucion actual. Los clasificadores se ejecutan dentro de las diez iteraciones del loop; `Check Ingest Result` se ejecuta despues, en la ejecucion `done`. En esa ejecucion no hay items de `Classify Stored Document`, por lo que `outcomes` queda vacio, `accepted` es `0`, `rejected` es `10` e `ingest_ok` es `false`.

La rama FALSE de `Ingest Batch Succeeded` llega a `Build Ingest Safe Error`, que responde deliberadamente:

```json
{
  "ok": false,
  "request_id": "del lote",
  "accepted": 0,
  "rejected": 10,
  "checksum": "del lote",
  "knowledge_version": 2,
  "activated": false
}
```

Laravel la rechaza porque `ok` no es `true`; `safePartialCounts()` conserva el rechazo tecnico y el comando informa `INVALID_INGEST_RESPONSE`, `accepted: 0`, `rejected: 10`.

Esto explica simultaneamente los diez documentos almacenados, cero recibos y el manifiesto Gemini inactivo: el Vector Store puede completar el insert, pero la contabilizacion posterior no ve los resultados de todas las ejecuciones del loop y nunca autoriza el recibo.

## Evidencia reproducible

`tests/node/n8n-assistant-ingest-forensic.test.mjs` conserva la causa como prueba de regresion. Carga el JSON Gemini regenerado y ejecuta con `node:vm` el `jsCode` real: diez resultados recibidos desde `done` producen `accepted: 10`, y el codigo ya no consulta `.all()` de clasificadores.

## Estado posterior a la Fase 2

Los diez documentos Gemini ya presentes no deben eliminarse ni duplicarse. No existen recibos Gemini ni manifiesto activo, asi que no constituyen una version activada. El script `repair-empty-gemini-rag.sql` no aplica: su guardia debe abortar cuando la tabla contiene documentos.

El nodo `@n8n/n8n-nodes-langchain.vectorStoreSupabase` devuelve documentos serializados y depende de item linking; no ofrece una confirmacion transaccional por lote ni un contador de inserciones/duplicados adecuado para este protocolo. Es aceptable para recuperacion RAG, pero no para determinar el exito de una ingesta idempotente y auditable.

La Fase 2 implemento esa decision en la fuente autoritativa: el resultado viaja como `{request, result}`, `Check Ingest Result` usa solo `$input.all()`, y la escritura persistente usa una RPC explicita validada y confirmada. El contrato y los pasos operativos de despliegue estan en `docs/assistant/rag-deterministic-ingest.md`.
