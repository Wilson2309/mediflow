# Fase 4: matriz de cobertura y simulacion local

Esta matriz documenta las comprobaciones locales de la ingesta RAG. No usa credenciales ni realiza llamadas a n8n, proveedores de embeddings o Supabase.

| Requisito | Pruebas | Caso positivo | Caso negativo | Estado |
| --- | --- | --- | --- | --- |
| Acumulacion por `done` y contrato por documento | `n8n-assistant-deterministic-ingest.test.mjs` 1-18 | Cada documento devuelve evidencia confirmada | Resultado faltante, ID repetido, checksum distinto, estado no permitido o `confirmed` no booleano | Cubierto |
| Recuperacion inicial | Determinista 5 y 7; simulacion Fase 4 | 10 existentes, sin embedding ni RPC, lote 0 aceptado | Identidad incompatible se vuelve fallo controlado | Cubierto |
| Recorrido de 17 lotes | `n8n-assistant-phase4-simulation.test.mjs` | 10 existentes + 151 nuevos, 161 aceptados y manifiesto final | Fallo en lote 5 detiene la continuidad antes del lote 6 | Cubierto |
| Reintento idempotente | Simulacion Fase 4 | 161 `already_present`, 0 embeddings, 0 RPC e inserciones nuevas | Recibo con valores incompatibles devuelve conflicto | Cubierto |
| Recibos y manifiesto | Simulacion Fase 4; `n8n-assistant-rag-repair.test.mjs` | Indices 0-16, 17 recibos y 161 documentos activan solo el lote final | Falta indice 8, hay 160 documentos, checksum mezclado o ID duplicado | Cubierto |
| RPC, trigger y privilegios SQL | `n8n-assistant-rag-repair.test.mjs` | RPC idempotente, `search_path` seguro y grants minimos | Conflicto de identidad, trigger sin cast explicito o grants inseguros | Cubierto |
| Grafo persistente | `n8n-assistant-ingest-confirmation.test.mjs` | Existente evita embedding; nuevo sigue embedding, RPC y confirmacion | Enlaces directos, RPC sin confirmacion, recibo prematuro o errores sin salida segura | Cubierto |
| Endpoint configurado una sola vez | Confirmacion y validador estructural | Un nodo de configuracion con marcador seguro reutilizado | Configuracion duplicada, host de ejemplo no protegido o endpoint no centralizado | Cubierto |
| Seguridad de webhook y configuracion | `n8n-assistant-hardening.test.mjs`, `n8n-assistant-final-security.test.mjs` | Cuerpo crudo, HMAC, timestamp, nonce y credenciales n8n | Secreto literal, host privado, destino invalido, tipo incompatible o anti-replay ausente | Cubierto |
| Contrato Laravel de siete campos | `AssistantN8nIngestFailureTest.php`, `AssistantN8nHardeningTest.php` | Exito valido y rechazo remoto tipado | Campo extra/faltante, tipos string, activacion intermedia o respuesta no estructural | Cubierto |
| Ocho workflows generados | Validador CLI y pruebas de workflow | Cuatro ingestas y cuatro consultas validan, con dimensiones correctas | Query alterada funcionalmente, rutas sin respuesta o secretos incorporados | Cubierto |

## Huecos encontrados y cerrados

- Faltaba una simulacion continua de los 17 lotes con 10 documentos ya presentes, manifiesto y reintento: se agrego `n8n-assistant-phase4-simulation.test.mjs`.
- Faltaba rechazar explicitamente mas de un nodo `Ingest Endpoint Configuration`: el validador ahora exige exactamente uno y una prueba negativa lo cubre.
- Se agrego la comprobacion de `storage_status` desconocido dentro de la simulacion de contrato por documento.

## Limite deliberado

La importacion y ejecucion contra servicios reales queda fuera de esta fase: se debe realizar despues con credenciales de n8n y Supabase configuradas manualmente, en un entorno controlado. Los workflows de consulta no se modifican funcionalmente.
