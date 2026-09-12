<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ApiContractFoundationTest extends TestCase
{
    public function test_api_generates_request_id(): void
    {
        $response = $this->getJson(
            '/api/v1/health'
        );

        $response->assertOk();

        $this->assertNotNull(
            $response->headers->get(
                'X-Request-ID'
            )
        );
    }

    public function test_api_preserves_client_request_id(): void
    {
        $response = $this
            ->withHeader(
                'X-Request-ID',
                'req-client-test'
            )
            ->getJson('/api/v1/health');

        $response->assertOk();

        $this->assertSame(
            'req-client-test',
            $response->headers->get(
                'X-Request-ID'
            )
        );
    }
}
