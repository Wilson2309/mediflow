# Proveedor remoto del Asistente MediFlow

## Arquitectura local-first

El widget consulta primero `resources/assistant/knowledge-base.json`. Una coincidencia clara o ambigua se resuelve completamente en el navegador. El endpoint Laravel sólo se consulta cuando no existe una coincidencia suficiente, la conexión reportada por `window.MediFlowConnection` es `ONLINE` y `ASSISTANT_REMOTE_ENABLED=true`.

El navegador nunca conoce la URL, el secreto ni el proveedor de n8n. Laravel valida el contexto, bloquea contenido sensible y es el único componente autorizado para realizar la solicitud externa.

## Continuidad en Fase 4

Este documento define el contrato Laravel. La configuración operativa de los workflows se encuentra en [n8n-full-setup.md](n8n-full-setup.md), la rotación y respuesta a incidentes en [n8n-security-operations.md](n8n-security-operations.md), y las pruebas sintéticas en [n8n-security-test-cases.md](n8n-security-test-cases.md). Supabase/pgvector es la variante persistente recomendada; Simple Vector Store es exclusivamente de desarrollo.

La consulta persistente exige un manifiesto activo válido antes de recuperar documentos. Si falta, su versión no coincide o Supabase falla, el flujo termina en fallback sin ejecutar vector store ni modelo. La búsqueda obtiene hasta 30 candidatos y el postfiltro vuelve a exigir rol, módulo permitido, `status=active`, locale y el mismo checksum activo; solo entonces reduce el contexto al Top K final configurable entre 3 y 10.

## Endpoint Laravel

- Método y ruta: `POST /assistant/message`
- Nombre: `assistant.message`
- Middleware: `auth`, `active_clinic` y `throttle:assistant`
- CSRF: requerido por el grupo `web`

El middleware `active_clinic` conserva la política existente: exige una clínica activa a usuarios operativos y permite que `super_admin` use el endpoint sin clínica asignada.

Campos aceptados:

- `question`: texto obligatorio, con máximo configurable.
- `current_route`: contexto opcional y no confiable, máximo 255 caracteres.
- `current_module`: módulo opcional incluido en el catálogo de la base de conocimiento.
- `connection_state`: `ONLINE`, `INTERNET_UNAVAILABLE`, `SERVER_UNAVAILABLE` u `OFFLINE`.
- `knowledge_version`: entero o texto corto opcional.

Se prohíben expresamente campos de identidad, autorización o datos sensibles, entre ellos `user_id`, `role`, `clinic_id`, `clinic_name`, `permissions`, `doctor_id`, `patient_id`, `payment_id`, `diagnosis`, `prescription`, `medical_record`, `card_number`, `password` y `token`.

El rol canónico, los permisos y la clínica activa se obtienen exclusivamente desde el usuario autenticado. La clínica puede formar parte de controles internos como middleware o rate limiting, pero nunca del payload remoto.

## Configuración

```dotenv
ASSISTANT_REMOTE_ENABLED=false
ASSISTANT_PROVIDER=null
ASSISTANT_N8N_WEBHOOK_URL=
ASSISTANT_N8N_SECRET=
ASSISTANT_TIMEOUT_SECONDS=8
ASSISTANT_RATE_LIMIT_PER_MINUTE=20
ASSISTANT_MAX_QUESTION_LENGTH=500
```

La integración está desactivada por defecto. Los únicos proveedores aceptados son `null` y `n8n`; no se resuelven nombres de clases desde el entorno. El timeout se limita entre 2 y 15 segundos y el límite por minuto entre 1 y 120. Aunque el proveedor sea `n8n`, una URL o secreto ausentes lo hacen no disponible.

Para activar una instalación preparada:

1. Definir una URL HTTPS privada y un secreto robusto en `.env`.
2. Establecer `ASSISTANT_PROVIDER=n8n`.
3. Establecer `ASSISTANT_REMOTE_ENABLED=true`.
4. Limpiar la caché de configuración con `php artisan config:clear`.

Para desactivar, usar `ASSISTANT_REMOTE_ENABLED=false`; el widget seguirá respondiendo localmente.

## Payload mínimo hacia n8n

```json
{
  "request_id": "uuid-generado-por-laravel",
  "question": "pregunta sanitizada",
  "role": "medico",
  "module": "prescriptions",
  "route": "prescriptions.index",
  "connection_state": "ONLINE",
  "locale": "es-EC",
  "knowledge_version": 1,
  "timestamp": "2026-07-12T12:00:00+00:00"
}
```

Nunca se envían nombre, correo, identificadores de usuario o clínica, permisos, datos de pacientes, diagnósticos, recetas reales, pagos, formularios, historial de conversación, cookies, sesión o token CSRF.

## Firma HMAC

Laravel serializa el cuerpo JSON una sola vez y envía:

- `X-MediFlow-Request-Id`
- `X-MediFlow-Timestamp`
- `X-MediFlow-Signature`
- `X-MediFlow-Assistant-Version`
- `Content-Type: application/json`

`X-MediFlow-Assistant-Version` transporta la versión del conocimiento indicada por Laravel. No identifica la versión de n8n, del workflow, del modelo de chat ni del modelo de embeddings.

La firma es:

```text
HMAC-SHA256(secret, timestamp + "." + jsonBody)
```

El workflow de consulta de Fase 4 reconstruye exactamente esa cadena con el cuerpo recibido sin modificar, calcula el HMAC con el mismo secreto, compara en tiempo constante, comprueba que el `request_id` coincida con la cabecera y rechaza timestamps antiguos para reducir reenvíos.

## Respuesta permitida

```json
{
  "answer": "Texto plano de ayuda.",
  "confidence": 0.91,
  "steps": ["Primer paso."],
  "suggestions": ["¿Necesitas otra guía?"],
  "can_escalate": false
}
```

Se acepta exactamente este contrato de cinco campos, todos obligatorios: `answer`, `confidence`, `steps`, `suggestions` y `can_escalate`. No se permiten campos adicionales ni ausentes. `answer` admite hasta 2.000 caracteres; `steps`, hasta 10 textos de 300 caracteres; `suggestions`, hasta 5 textos de 150 caracteres; y `confidence`, un número entre 0 y 1. Se descartan HTML, scripts, iframes, JavaScript, URLs externas, comandos o rutas de acción. El widget renderiza con `textContent`, nunca como HTML.

## Privacidad y fallbacks

Antes de llamar al proveedor se buscan correos, teléfonos, identificaciones largas, tarjetas, credenciales explícitas y expresiones que probablemente acompañen datos de pacientes o información clínica. Si se detectan, no se realiza ninguna solicitud externa y se pide reformular sin datos sensibles. Una pregunta general como “¿Cómo creo una receta médica?” permanece permitida.

Errores de configuración, timeout, red, HTTP, JSON o esquema producen el fallback seguro:

> No encontré una respuesta exacta para eso. Puedes revisar la guía del módulo o contactar al administrador.

Las respuestas no revelan URL, secreto, excepción, payload o clases internas. Los logs de error de Laravel contienen únicamente metadata técnica segura: `request_id`, proveedor, estado, duración, rol, módulo y código interno; nunca la pregunta o respuesta completa.

En n8n se construye telemetría técnica permitida, pero su persistencia queda deliberadamente pendiente. Los workflows usan `saveDataSuccessExecution=none`, `saveDataErrorExecution=none`, desactivan el guardado manual y no tienen un sink de observabilidad. No debe habilitarse el guardado completo como sustituto, porque retendría pregunta, respuesta y contexto documental.

## Rate limiting

`throttle:assistant` usa internamente usuario, clínica activa (o `global` para `super_admin`) e IP. El valor predeterminado es 20 solicitudes por minuto y no afecta otras rutas. Al excederlo responde HTTP 429 sin consultar al proveedor.

## Pruebas con Http::fake()

Las pruebas activan configuración sólo dentro del proceso de test y usan una URL reservada de ejemplo:

```php
Http::fake([
    'https://n8n.example.test/*' => Http::response([
        'answer' => 'Respuesta segura',
        'confidence' => 0.9,
        'steps' => [],
        'suggestions' => [],
        'can_escalate' => false,
    ]),
]);
```

`Http::assertSent()` comprueba método, URL, payload mínimo y cabeceras HMAC. También se simulan timeout, error HTTP, JSON inválido y respuestas inseguras. Ninguna prueba realiza tráfico real.

## Activación de la fase n8n

Los workflows versionados preparan HMAC, timestamp, antireplay, RAG por rol/módulo, parser estructurado y validación final. Aun así, importar los JSON no activa el servicio: el operador debe seleccionar credenciales, tablas, funciones RPC y modelos en n8n Cloud, ejecutar las pruebas integrales en un entorno aislado y mantener `ASSISTANT_REMOTE_ENABLED=false` hasta aprobarlas.

Cuando cambien `knowledge-base.json`, el prompt del sistema o los catálogos de roles/módulos, se deben ejecutar `npm.cmd run assistant:n8n-documents` y `npm.cmd run assistant:n8n-workflows`, validar los artefactos y reimportar en n8n el par elegido. Regenerar solo los documentos puede dejar el contrato embebido del workflow desactualizado.
