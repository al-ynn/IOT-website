<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseCandidateSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_is_public_bounded_and_non_diagnostic(): void
    {
        $response = $this->getJson('/api/health/readiness')->assertOk()->assertExactJson(['status' => 'ready']);
        foreach (['database', 'driver', 'host', 'environment', 'version', 'password'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, strtolower($response->getContent()));
        }
    }

    public function test_public_registration_remains_closed(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Attack', 'email' => 'attack@example.com', 'password' => 'password123'])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'attack@example.com']);
    }
}
