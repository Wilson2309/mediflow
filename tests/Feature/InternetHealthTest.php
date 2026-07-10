<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class InternetHealthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.internet_health.urls' => ['https://connectivity.test/health'],
            'services.internet_health.timeout' => 2,
        ]);
        Http::preventStrayRequests();
    }

    public function test_internet_health_returns_expected_minimal_structure(): void
    {
        Http::fake(['connectivity.test/*' => Http::response(null, 204)]);

        $response = $this->getJson('/internet-health')
            ->assertOk()
            ->assertJsonStructure(['ok', 'internet', 'timestamp'])
            ->assertJson(['ok' => true, 'internet' => true]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_external_check_can_be_faked_without_real_internet_calls(): void
    {
        Http::fake(['connectivity.test/*' => Http::response('', 200)]);

        $this->getJson('/internet-health')->assertJson(['internet' => true]);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) =>
            $request->url() === 'https://connectivity.test/health'
            && $request->data() === []
            && ! str_contains($request->url(), 'clinic'));
    }

    public function test_timeout_or_connection_error_returns_internet_false(): void
    {
        Http::fake(fn () => throw new ConnectionException('Simulated timeout'));

        $this->getJson('/internet-health')
            ->assertOk()
            ->assertJson(['ok' => true, 'internet' => false]);
    }

    public function test_external_exception_is_not_exposed(): void
    {
        Http::fake(fn () => throw new RuntimeException('internal-secret-value'));

        $response = $this->getJson('/internet-health')->assertOk();

        $response->assertJson(['ok' => true, 'internet' => false]);
        $this->assertStringNotContainsString('internal-secret-value', $response->getContent());
        $this->assertStringNotContainsString('exception', strtolower($response->getContent()));
    }

    public function test_internet_health_does_not_expose_sensitive_data(): void
    {
        Http::fake(['connectivity.test/*' => Http::response(null, 503)]);

        $payload = $this->getJson('/internet-health')->assertOk()->json();

        $this->assertSame(['ok', 'internet', 'timestamp'], array_keys($payload));
        foreach (['database', 'environment', 'debug', 'user', 'clinic_id', 'url', 'error', 'exception'] as $key) {
            $this->assertArrayNotHasKey($key, $payload);
        }
    }

    public function test_successful_external_response_returns_internet_true(): void
    {
        Http::fake(['connectivity.test/*' => Http::response(null, 204)]);

        $this->getJson('/internet-health')
            ->assertOk()
            ->assertJson(['ok' => true, 'internet' => true]);
    }
}
