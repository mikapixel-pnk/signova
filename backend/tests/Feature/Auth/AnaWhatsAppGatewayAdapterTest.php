<?php

namespace Tests\Feature\Auth;

use App\Contracts\Messaging\WhatsAppDelivery;
use App\Exceptions\Integration\WhatsAppDeliveryException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnaWhatsAppGatewayAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'services.ana_whatsapp.base_url',
            'https://ana.example.test'
        );

        config()->set(
            'services.ana_whatsapp.sender',
            '087739029392'
        );

        config()->set(
            'services.ana_whatsapp.api_key',
            'test-secret-key'
        );
    }

    public function test_it_sends_normalized_number_as_json(): void
    {
        Http::fake([
            'https://ana.example.test/send-message' =>
                Http::response([
                    'status' => true,
                    'msg' =>
                        'Message sent successfully!',
                ], 200),
        ]);

        app(
            WhatsAppDelivery::class
        )->send(
            '+62 812-3456-7890',
            'Kode verifikasi SIGNOVA: 123456'
        );

        Http::assertSent(
            function ($request): bool {
                return
                    $request->url()
                        ===
                        'https://ana.example.test/send-message'
                    && $request[
                        'api_key'
                    ] ===
                        'test-secret-key'
                    && $request[
                        'sender'
                    ] ===
                        '6287739029392'
                    && $request[
                        'number'
                    ] ===
                        '6281234567890'
                    && $request[
                        'message'
                    ] ===
                        'Kode verifikasi SIGNOVA: 123456';
            }
        );
    }

    public function test_gateway_status_false_is_rejected(): void
    {
        Http::fake([
            '*' =>
                Http::response([
                    'status' => false,
                    'msg' => 'Failed',
                ], 200),
        ]);

        $this->expectException(
            WhatsAppDeliveryException::class
        );

        app(
            WhatsAppDelivery::class
        )->send(
            '081234567890',
            'Kode verifikasi'
        );
    }

    public function test_http_error_is_rejected(): void
    {
        Http::fake([
            '*' =>
                Http::response(
                    ['status' => false],
                    500
                ),
        ]);

        $this->expectException(
            WhatsAppDeliveryException::class
        );

        app(
            WhatsAppDelivery::class
        )->send(
            '081234567890',
            'Kode verifikasi'
        );
    }

    public function test_missing_secret_configuration_is_rejected(): void
    {
        config()->set(
            'services.ana_whatsapp.api_key',
            ''
        );

        Http::fake();

        $this->expectException(
            WhatsAppDeliveryException::class
        );

        app(
            WhatsAppDelivery::class
        )->send(
            '081234567890',
            'Kode verifikasi'
        );
    }
}
