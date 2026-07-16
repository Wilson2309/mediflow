# Despliegue definitivo de la ingesta Gemini en n8n

Esta es la unica ruta operativa para la ingesta RAG persistente de MediFlow. Conserva el enfoque local-first: el widget responde primero con conocimiento local y Laravel es el unico proceso que puede llamar a n8n. No modifica los workflows de consulta.

El workflow que se importa para esta ruta es exclusivamente [mediflow-assistant-ingest-supabase-gemini.json](../../n8n/workflows/mediflow-assistant-ingest-supabase-gemini.json). No publicar dos workflows con la misma ruta de ingesta.

## Reglas antes de desplegar

- No eliminar los 10 documentos Gemini ya existentes: el lote 0 los reconocera como `already_present`, sin embedding ni RPC de insercion.
- No ejecutar [repair-empty-gemini-rag.sql](../../n8n/supabase/repair-empty-gemini-rag.sql) si la tabla Gemini contiene documentos, recibos o un manifiesto activo. Es una reparacion exclusiva para una tabla vacia y aborta fuera de ese estado.
- Usar la Production URL despues de publicar. No usar `webhook-test` ni copiar URLs, secretos o service-role keys dentro de nodos Code.
- El marcador `your-project.supabase.co` es deliberado y bloquea el workflow hasta su configuracion manual.

## Ruta manual unica: 10 pasos maximos

1. Despublicar o eliminar el workflow de ingesta Gemini anterior que use la misma ruta. Mantener los workflows de consulta sin cambios.
2. Revisar y ejecutar [assistant-rag-schema.sql](../../n8n/supabase/assistant-rag-schema.sql) en el SQL Editor del proyecto privado de Supabase. No ejecutar fragmentos ni el repair sobre una tabla no vacia.
3. Ejecutar `NOTIFY pgrst, 'reload schema';` en el SQL Editor de Supabase despues de aplicar el SQL, para que las RPC y grants nuevos sean visibles.
4. Importar [mediflow-assistant-ingest-supabase-gemini.json](../../n8n/workflows/mediflow-assistant-ingest-supabase-gemini.json) y mantenerlo inactivo.
5. Abrir el unico nodo **Ingest Endpoint Configuration** y sustituir `https://your-project.supabase.co` por la URL base HTTPS del proyecto autorizado, sin ruta ni clave.
6. Seleccionar en n8n las credenciales preexistentes: **MEDIFLOW_HMAC_INGEST_SECRET** para Crypto, **MEDIFLOW_SUPABASE** para HTTP/Supabase y **MEDIFLOW_GEMINI** para embeddings. Nunca pegar sus valores en un nodo Code o JSON.
7. Guardar y publicar el workflow. Copiar solamente su Production URL al canal de configuracion privado de Laravel; no usar Test URL ni exponerla al navegador.
8. Ejecutar localmente `php artisan assistant:diagnose-n8n-ingest`. Debe indicar 161 documentos, 17 lotes, workflow Gemini valido, marcador presente en el JSON versionado y `Remote requests performed: no`.
9. Ejecutar `php artisan assistant:sync-n8n-knowledge --dry-run --provider=supabase --batch=10`. Despues de revisar el resumen, ejecutar el mismo comando sin `--dry-run` en una ventana controlada.
10. Ejecutar las consultas SQL de verificacion siguientes. El resultado esperado es 161 documentos distintos, 17 recibos de indice 0 a 16 y un manifiesto Gemini activo de version 2.

## Diagnostico local

`php artisan assistant:diagnose-n8n-ingest` no envia solicitudes y no imprime URLs, secretos, firmas, documentos, metadata ni credenciales. Comprueba el paquete documental, su checksum, el workflow Gemini generado, el marcador del host, el validador estructural y los dos archivos SQL.

La opcion `--remote-test` es deliberadamente excepcional: requiere tambien `--confirm-remote-test`, URL y secreto configurados, y puede modificar Supabase con un unico documento sintetico. No usarla como parte de la validacion normal.

## Contrato del workflow Gemini

El JSON definitivo exige cuerpo crudo, HMAC SHA-256, timestamp estricto, anti-replay, un unico host centralizado y errores seguros. Para cada documento:

1. Consulta `document_id` y `knowledge_checksum` existentes.
2. Si ya existe, devuelve `already_present` y omite embedding/RPC.
3. Si no existe, genera solo el embedding Gemini, valida exactamente 3072 numeros finitos, llama la RPC idempotente y confirma la fila.
4. Devuelve evidencia por documento al Loop Over Items.
5. La salida `done` acumula con `$input.all()`, crea el recibo idempotente y solo el lote final puede activar el manifiesto.

La respuesta externa contiene exactamente `ok`, `request_id`, `accepted`, `rejected`, `checksum`, `knowledge_version` y `activated`.

## SQL final y verificacion en Supabase

El esquema principal crea `vector(3072)`, `metadata jsonb`, trigger con conversion explicita a `jsonb`, RPC idempotentes, recibos, manifiestos, indices y grants restringidos a `service_role`. Tras aplicarlo, recargar PostgREST antes de publicar.

Usar el checksum actual sin reemplazarlo por valores no verificados:

```sql
select
    count(*) as documents,
    count(distinct document_id) as distinct_documents
from public.assistant_documents_gemini_3072
where knowledge_checksum =
'3426dea3a19861815b95bd744a8a1ca195290ad654b50b883924374ab4df929e';
```

Esperado: `documents = 161`, `distinct_documents = 161`.

```sql
select
    count(*) as receipts,
    min(batch_index) as first_batch,
    max(batch_index) as last_batch,
    sum(accepted_count) as accepted_total
from public.assistant_ingest_batches
where provider = 'gemini'
  and checksum =
'3426dea3a19861815b95bd744a8a1ca195290ad654b50b883924374ab4df929e';
```

La columna autoritativa se llama `accepted_count`. Esperado: `receipts = 17`, `first_batch = 0`, `last_batch = 16`, `accepted_total = 161`.

```sql
select batch_index
from public.assistant_ingest_batches
where provider = 'gemini'
  and checksum =
'3426dea3a19861815b95bd744a8a1ca195290ad654b50b883924374ab4df929e'
order by batch_index;
```

Esperado: indices consecutivos de `0` a `16`.

```sql
select provider, knowledge_version, document_count, active_checksum, activated_at
from public.assistant_knowledge_manifests
where provider = 'gemini';
```

Esperado: `gemini`, version `2`, `document_count = 161` y el checksum anterior activo.

```sql
select document_id, knowledge_checksum, count(*)
from public.assistant_documents_gemini_3072
group by document_id, knowledge_checksum
having count(*) > 1;
```

Esperado: cero filas.

## Limites operativos

El validador local comprueba los ocho workflows y sus tipos/versiones conocidos. La importacion real sigue requiriendo confirmar en la cuenta n8n Cloud que las credenciales y los nodos Gemini estan disponibles. No habilitar asistencia remota ni eliminar una version anterior hasta completar las consultas de verificacion.
