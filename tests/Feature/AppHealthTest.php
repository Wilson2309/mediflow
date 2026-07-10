<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppHealthTest extends TestCase
{
    public function test_app_health_returns_minimal_ok_json(): void
    {
        $response = $this->getJson('/app-health')
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'app' => 'MediFlow',
            ])
            ->assertJsonStructure([
                'ok',
                'timestamp',
                'app',
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_app_health_does_not_expose_sensitive_data(): void
    {
        $payload = $this->getJson('/app-health')
            ->assertOk()
            ->json();

        $this->assertSame(['ok', 'timestamp', 'app'], array_keys($payload));
        $this->assertArrayNotHasKey('database', $payload);
        $this->assertArrayNotHasKey('environment', $payload);
        $this->assertArrayNotHasKey('debug', $payload);
        $this->assertArrayNotHasKey('user', $payload);
    }
}
