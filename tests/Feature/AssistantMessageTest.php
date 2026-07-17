<?php

namespace Tests\Feature;

use App\Data\AssistantResult;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AssistantMessageTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://n8n.example.test/webhook/assistant';

    private const SECRET = 'phase-three-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'assistant.remote_enabled' => true,
            'assistant.provider' => 'n8n',
            'assistant.n8n.webhook_url' => self::URL,
            'assistant.n8n.secret' => self::SECRET,
            'assistant.timeout_seconds' => 8,
            'assistant.rate_limit_per_minute' => 120,
            'assistant.max_question_length' => 500,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_endpoint(): void
    {
        $this->postJson(route('assistant.message'), $this->payload())
            ->assertRedirect(route('login'));

        Http::assertNothingSent();
    }

    public function test_authenticated_user_can_receive_valid_remote_answer(): void
    {
        Http::fake([self::URL => $this->validRemoteResponse()]);

        $this->actingAs($this->userForClinic('medico'))
            ->postJson(route('assistant.message'), $this->payload())
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'answer' => 'Abre el módulo de ayuda avanzada.',
                'steps' => ['Selecciona la opción disponible.'],
                'suggestions' => ['¿Qué hago después?'],
                'confidence' => 0.91,
                'source' => 'remote',
                'fallback_used' => false,
            ])
            ->assertJsonStructure(['request_id']);
    }

    public function test_server_role_and_minimal_context_are_sent_without_identity_or_clinic_data(): void
    {
        Http::fake([self::URL => $this->validRemoteResponse()]);
        $user = $this->userForClinic('recepcionista');

        $this->actingAs($user)
            ->postJson(route('assistant.message'), $this->payload())
            ->assertOk();

        Http::assertSent(function (Request $request) use ($user): bool {
            $data = $request->data();

            $this->assertSame('recepcionista', $data['role']);
            $this->assertSame('support', $data['module']);
            $this->assertSame('dashboard', $data['route']);
            $this->assertSame('ONLINE', $data['connection_state']);
            $this->assertSame('es-EC', $data['locale']);
            $this->assertArrayHasKey('request_id', $data);
            $this->assertArrayHasKey('timestamp', $data);

            foreach (['user_id', 'clinic_id', 'clinic_name', 'name', 'email', 'permissions', 'patient_id', 'payment_id'] as $forbidden) {
                $this->assertArrayNotHasKey($forbidden, $data);
            }

            $this->assertStringNotContainsString($user->name, $request->body());
            $this->assertStringNotContainsString($user->email, $request->body());

            return true;
        });
    }

    public function test_client_identity_authorization_and_clinic_fields_are_prohibited(): void
    {
        $user = $this->userForClinic('medico');

        foreach (['user_id' => 999, 'role' => 'super_admin', 'clinic_id' => 999] as $field => $value) {
            $this->actingAs($user)
                ->postJson(route('assistant.message'), [...$this->payload(), $field => $value])
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }

        Http::assertNothingSent();
    }

    public function test_all_sensitive_and_server_owned_fields_are_prohibited(): void
    {
        $user = $this->userForClinic();
        $fields = [
            'clinic_name', 'permissions', 'doctor_id', 'patient_id', 'payment_id',
            'diagnosis', 'prescription', 'medical_record', 'card_number', 'password', 'token',
        ];

        foreach ($fields as $field) {
            $this->actingAs($user)
                ->postJson(route('assistant.message'), [...$this->payload(), $field => 'forbidden'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }

        Http::assertNothingSent();
    }

    public function test_question_too_long_and_invalid_module_return_validation_errors(): void
    {
        $user = $this->userForClinic();

        $this->actingAs($user)
            ->postJson(route('assistant.message'), [...$this->payload(), 'question' => str_repeat('a', 501)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question');

        $this->actingAs($user)
            ->postJson(route('assistant.message'), [...$this->payload(), 'current_module' => 'unknown-module'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_module');

        Http::assertNothingSent();
    }

    public function test_remote_disabled_returns_exact_safe_fallback(): void
    {
        config(['assistant.remote_enabled' => false]);

        $this->actingAs($this->userForClinic())
            ->postJson(route('assistant.message'), $this->payload())
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'answer' => AssistantResult::FALLBACK_ANSWER,
                'source' => 'fallback',
                'fallback_used' => true,
                'code' => 'REMOTE_DISABLED',
            ]);

        Http::assertNothingSent();
    }

    public function test_null_or_unknown_provider_returns_fallback_without_external_request(): void
    {
        foreach ([null, 'arbitrary-class'] as $provider) {
            config(['assistant.provider' => $provider]);

            $this->actingAs($this->userForClinic())
                ->postJson(route('assistant.message'), $this->payload())
                ->assertOk()
                ->assertJson([
                    'source' => 'fallback',
                    'fallback_used' => true,
                    'code' => 'PROVIDER_UNAVAILABLE',
                ]);
        }

        Http::assertNothingSent();
    }

    public function test_missing_n8n_url_or_secret_returns_provider_unavailable(): void
    {
        $user = $this->userForClinic();

        foreach ([['', self::SECRET], [self::URL, '']] as [$url, $secret]) {
            config(['assistant.n8n.webhook_url' => $url, 'assistant.n8n.secret' => $secret]);

            $this->actingAs($user)
                ->postJson(route('assistant.message'), $this->payload())
                ->assertOk()
                ->assertJson(['code' => 'PROVIDER_UNAVAILABLE', 'fallback_used' => true]);
        }

        Http::assertNothingSent();
    }

    public function test_n8n_request_uses_post_correct_url_minimal_json_and_valid_hmac_headers(): void
    {
        Http::fake([self::URL => $this->validRemoteResponse()]);

        $response = $this->actingAs($this->userForClinic('medico'))
            ->postJson(route('assistant.message'), $this->payload())
            ->assertOk();

        Http::assertSent(function (Request $request) use ($response): bool {
            $timestamp = $request->header('X-MediFlow-Timestamp')[0] ?? '';
            $requestId = $request->header('X-MediFlow-Request-Id')[0] ?? '';
            $signature = $request->header('X-MediFlow-Signature')[0] ?? '';

            $this->assertSame(self::URL, $request->url());
            $this->assertSame('POST', $request->method());
            $this->assertSame($response->json('request_id'), $requestId);
            $this->assertSame($request->data()['timestamp'], $timestamp);
            $this->assertSame(hash_hmac('sha256', $timestamp.'.'.$request->body(), self::SECRET), $signature);
            $this->assertSame('2', $request->header('X-MediFlow-Assistant-Version')[0] ?? null);
            $this->assertStringStartsWith('application/json', $request->header('Content-Type')[0] ?? '');
            $this->assertSame(
                ['request_id', 'question', 'role', 'module', 'route', 'connection_state', 'locale', 'knowledge_version', 'timestamp', 'allowed_modules'],
                array_keys($request->data()),
            );

            return true;
        });
    }

    public function test_receptionist_remote_payload_excludes_privileged_modules(): void
    {
        Http::fake([self::URL => $this->validRemoteResponse()]);

        $this->actingAs($this->userForClinic('recepcionista'))
            ->postJson(route('assistant.message'), $this->payload())
            ->assertOk();

        Http::assertSent(function (Request $request): bool {
            $allowed = $request->data()['allowed_modules'] ?? [];
            $this->assertContains('appointments', $allowed);
            $this->assertContains('patients', $allowed);
            foreach (['payments', 'reports', 'financial_audit', 'audit', 'medical_records', 'prescriptions'] as $forbidden) {
                $this->assertNotContains($forbidden, $allowed);
            }

            return true;
        });
    }

    public function test_connection_timeout_and_http_error_return_fallback(): void
    {
        $user = $this->userForClinic();
        Http::fake(fn () => throw new ConnectionException('simulated timeout with private detail'));

        $timeout = $this->actingAs($user)
            ->postJson(route('assistant.message'), $this->payload())
            ->assertOk()
            ->assertJson(['code' => 'PROVIDER_UNAVAILABLE', 'fallback_used' => true]);

        $this->assertStringNotContainsString('private detail', $timeout->getContent());
        $this->assertStringNotContainsString(self::SECRET, $timeout->getContent());

        Http::fake([self::URL => Http::response(['internal' => 'do-not-expose'], 503)]);

        $httpError = $this->actingAs($user)
            ->postJson(route('assistant.message'), $this->payload())
            ->assertOk()
            ->assertJson(['code' => 'PROVIDER_UNAVAILABLE', 'fallback_used' => true]);

        $this->assertStringNotContainsString('do-not-expose', $httpError->getContent());
    }

    public function test_invalid_json_empty_or_invalid_schema_returns_fallback(): void
    {
        $user = $this->userForClinic();
        $responses = [
            Http::response('{not-json', 200, ['Content-Type' => 'application/json']),
            Http::response([], 200),
            Http::response(['answer' => 'Valid text', 'unexpected' => 'field'], 200),
            Http::response(['answer' => 'Valid text', 'role' => 'super_admin'], 200),
        ];

        foreach ($responses as $fakeResponse) {
            Http::fake([self::URL => $fakeResponse]);

            $this->actingAs($user)
                ->postJson(route('assistant.message'), $this->payload())
                ->assertOk()
                ->assertJson(['code' => 'INVALID_REMOTE_RESPONSE', 'fallback_used' => true]);
        }
    }

    public function test_html_script_url_and_action_commands_are_rejected(): void
    {
        $user = $this->userForClinic();

        foreach ([
            '<b>Texto HTML</b>',
            '<script>alert(1)</script>',
            'Visita https://evil.example para continuar',
            'POST /patients/1/delete',
            'Abre /admin/secret para continuar',
            'Ejecuta php artisan migrate',
        ] as $unsafeAnswer) {
            Http::fake([self::URL => Http::response(['answer' => $unsafeAnswer], 200)]);

            $response = $this->actingAs($user)
                ->postJson(route('assistant.message'), $this->payload())
                ->assertOk()
                ->assertJson(['code' => 'INVALID_REMOTE_RESPONSE', 'fallback_used' => true]);

            $this->assertStringNotContainsString($unsafeAnswer, $response->getContent());
        }
    }

    public function test_valid_remote_response_delivers_controlled_fields(): void
    {
        Http::fake([self::URL => $this->validRemoteResponse()]);

        $payload = $this->actingAs($this->userForClinic())
            ->postJson(route('assistant.message'), $this->payload())
            ->assertOk()
            ->json();

        $this->assertSame(
            ['ok', 'request_id', 'answer', 'steps', 'suggestions', 'confidence', 'source', 'can_escalate', 'fallback_used'],
            array_keys($payload),
        );
    }

    public function test_email_identification_and_clinical_patient_data_are_blocked_before_n8n(): void
    {
        $user = $this->userForClinic();

        foreach ([
            'Ayuda para correo paciente@example.com',
            'Cómo creo una receta para Juan Pérez con cédula 0912345678',
            'Cuál es el diagnóstico de María López',
            'Historia clínica de Pedro Pérez',
        ] as $question) {
            $this->actingAs($user)
                ->postJson(route('assistant.message'), [...$this->payload(), 'question' => $question])
                ->assertUnprocessable()
                ->assertJson([
                    'ok' => false,
                    'answer' => AssistantResult::SENSITIVE_ANSWER,
                    'code' => 'SENSITIVE_CONTENT',
                ]);
        }

        Http::assertNothingSent();
    }

    public function test_generic_recipe_how_to_question_is_not_blocked(): void
    {
        Http::fake([self::URL => $this->validRemoteResponse()]);

        $this->actingAs($this->userForClinic('medico'))
            ->postJson(route('assistant.message'), [
                ...$this->payload(),
                'question' => '¿Cómo creo una receta médica?',
                'current_module' => 'prescriptions',
            ])
            ->assertOk()
            ->assertJson(['source' => 'remote']);

        Http::assertSentCount(1);
    }

    public function test_rate_limit_returns_429_and_does_not_send_limited_request(): void
    {
        config(['assistant.rate_limit_per_minute' => 1]);
        Http::fake([self::URL => $this->validRemoteResponse()]);
        $user = $this->userForClinic();
        $key = "assistant:{$user->id}:{$user->clinic_id}:127.0.0.1";
        RateLimiter::clear($key);

        $this->actingAs($user)
            ->postJson(route('assistant.message'), $this->payload())
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('assistant.message'), $this->payload('segunda pregunta desconocida'))
            ->assertTooManyRequests()
            ->assertJson([
                'ok' => false,
                'code' => 'RATE_LIMITED',
                'answer' => 'Has realizado demasiadas preguntas en poco tiempo. Espera un momento e inténtalo nuevamente.',
            ]);

        Http::assertSentCount(1);
    }

    public function test_super_admin_without_clinic_can_use_endpoint_with_global_role(): void
    {
        Http::fake([self::URL => $this->validRemoteResponse()]);
        $superAdmin = User::factory()->create(['clinic_id' => null, 'current_clinic_id' => null]);
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)
            ->postJson(route('assistant.message'), $this->payload())
            ->assertOk()
            ->assertJson(['source' => 'remote']);

        Http::assertSent(fn (Request $request): bool => $request->data()['role'] === 'super_admin'
            && ! array_key_exists('clinic_id', $request->data()));
    }

    public function test_user_with_inactive_clinic_remains_blocked(): void
    {
        $user = $this->userForClinic('administrador', 'inactive');

        $this->actingAs($user)
            ->postJson(route('assistant.message'), $this->payload())
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_remote_failure_never_exposes_secret_url_exception_or_payload(): void
    {
        Http::fake(fn () => throw new \RuntimeException('private-exception-marker'));
        $question = 'pregunta técnica desconocida sin información personal';

        $content = $this->actingAs($this->userForClinic())
            ->postJson(route('assistant.message'), $this->payload($question))
            ->assertOk()
            ->getContent();

        foreach ([self::SECRET, self::URL, 'private-exception-marker', $question, 'N8nAssistantProvider'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $content);
        }
    }

    /** @return array<string, mixed> */
    private function payload(string $question = 'pregunta técnica avanzada sin coincidencia local'): array
    {
        return [
            'question' => $question,
            'current_route' => 'dashboard',
            'current_module' => 'support',
            'connection_state' => 'ONLINE',
            'knowledge_version' => 2,
        ];
    }

    private function validRemoteResponse(): mixed
    {
        return Http::response([
            'answer' => 'Abre el módulo de ayuda avanzada.',
            'confidence' => 0.91,
            'steps' => ['Selecciona la opción disponible.'],
            'suggestions' => ['¿Qué hago después?'],
            'can_escalate' => false,
        ], 200);
    }

    private function userForClinic(string $role = 'administrador', string $clinicStatus = 'active'): User
    {
        $clinic = Clinic::factory()->create(['status' => $clinicStatus]);
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'current_clinic_id' => $clinic->id,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
