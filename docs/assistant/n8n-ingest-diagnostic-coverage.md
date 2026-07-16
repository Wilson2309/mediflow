# Cobertura del diagnostico de ingesta n8n

El comando `assistant:diagnose-n8n-ingest` se prueba sin servicios externos mediante `Http::fake()` en `AssistantN8nIngestDiagnosticTest`.

| Escenario requerido | Prueba que lo cubre |
| --- | --- |
| Diagnostico local correcto | `test_local_diagnostic_validates_the_package_workflow_and_sql_without_http` |
| URL de ingesta ausente | `test_diagnostic_reports_missing_url_and_secret_without_exposing_values` |
| Secreto HMAC ausente | `test_diagnostic_reports_missing_url_and_secret_without_exposing_values` |
| Workflow Gemini ausente | `test_diagnostic_fails_for_a_missing_or_invalid_workflow` |
| Workflow estructuralmente invalido | `test_diagnostic_fails_for_a_missing_or_invalid_workflow` |
| Paquete documental invalido | `test_diagnostic_fails_for_an_invalid_package_and_never_prints_documents` |
| Marcador Supabase presente | `test_local_diagnostic_validates_the_package_workflow_and_sql_without_http` |
| Sin secreto ni URL completa en salida | `test_diagnostic_reports_missing_url_and_secret_without_exposing_values` y `test_confirmed_remote_test_uses_one_synthetic_document_and_safe_output` |
| Sin HTTP por defecto | `test_local_diagnostic_validates_the_package_workflow_and_sql_without_http` |
| `--remote-test` exige confirmacion y configuracion completa | `test_remote_test_requires_confirmation_and_complete_configuration` |

Las seis pruebas cubren los diez escenarios; no se requieren diez metodos separados.
