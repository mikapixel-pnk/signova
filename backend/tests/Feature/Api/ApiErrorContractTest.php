<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiErrorContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')
            ->get(
                '/api/v1/test/internal-error',
                function () {
                    throw new RuntimeException(
                        'Sensitive internal error.'
                    );
                }
            );
    }

    public function test_validation_error_uses_standard_contract(): void
    {
        $response = $this->postJson(
            '/api/v1/auth/login',
            []
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            )
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'fields',
                    ],
                ],
                'meta' => [
                    'request_id',
                ],
            ]);

        $this->assertNotNull(
            $response->headers->get(
                'X-Request-ID'
            )
        );
    }

    public function test_unauthenticated_error_uses_standard_contract(): void
    {
        $response = $this->getJson(
            '/api/v1/auth/me'
        );

        $response
            ->assertUnauthorized()
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'error.code',
                'AUTH_REQUIRED'
            );
    }

    public function test_missing_api_resource_uses_standard_contract(): void
    {
        $response = $this->getJson(
            '/api/v1/resource-that-does-not-exist'
        );

        $response
            ->assertNotFound()
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );

        $this->assertNotNull(
            $response->headers->get(
                'X-Request-ID'
            )
        );
    }

    public function test_internal_error_does_not_leak_exception_message(): void
    {
        $response = $this->getJson(
            '/api/v1/test/internal-error'
        );

        $response
            ->assertStatus(500)
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'error.code',
                'INTERNAL_ERROR'
            )
            ->assertJsonMissing([
                'message' =>
                    'Sensitive internal error.',
            ]);

        $this->assertStringNotContainsString(
            'Sensitive internal error.',
            $response->getContent()
        );
    }
}
