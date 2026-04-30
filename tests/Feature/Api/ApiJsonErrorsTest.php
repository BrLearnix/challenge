<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ApiJsonErrorsTest extends TestCase
{
    public function test_get_on_payments_returns_json_method_not_allowed(): void
    {
        $response = $this->getJson('/api/v1/payments');

        $response->assertStatus(405)
            ->assertHeader('Allow')
            ->assertJsonStructure(['error', 'message', 'allowed_methods', 'hint'])
            ->assertJsonPath('error', 'method_not_allowed');
    }

    public function test_unknown_api_route_returns_json_not_found(): void
    {
        $response = $this->getJson('/api/v1/no-existe');

        $response->assertStatus(404)
            ->assertJsonStructure(['error', 'message', 'path', 'hint'])
            ->assertJsonPath('error', 'not_found');
    }
}
