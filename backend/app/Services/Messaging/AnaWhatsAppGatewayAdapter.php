<?php

namespace App\Services\Messaging;

use App\Contracts\Messaging\WhatsAppDelivery;
use App\Exceptions\Integration\WhatsAppDeliveryException;
use App\Support\Phone\IndonesianPhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AnaWhatsAppGatewayAdapter implements WhatsAppDelivery
{
    public function send(
        string $number,
        string $message
    ): void {
        $baseUrl = rtrim(
            (string) config(
                'services.ana_whatsapp.base_url'
            ),
            '/'
        );

        $sender = IndonesianPhoneNumber::normalize(
            (string) config(
                'services.ana_whatsapp.sender'
            )
        );

        $apiKey = trim(
            (string) config(
                'services.ana_whatsapp.api_key'
            )
        );

        $recipient =
            IndonesianPhoneNumber::normalize(
                $number
            );

        if (
            $baseUrl === ''
            || $sender === null
            || $apiKey === ''
            || $recipient === null
        ) {
            throw new WhatsAppDeliveryException(
                'Konfigurasi WhatsApp Gateway belum lengkap.'
            );
        }

        if (trim($message) === '') {
            throw new WhatsAppDeliveryException(
                'Pesan WhatsApp tidak boleh kosong.'
            );
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(3)
                ->timeout(8)
                ->post(
                    $baseUrl . '/send-message',
                    [
                        'api_key' =>
                            $apiKey,

                        'sender' =>
                            $sender,

                        'number' =>
                            $recipient,

                        'message' =>
                            $message,
                    ]
                );
        } catch (ConnectionException $exception) {
            throw new WhatsAppDeliveryException(
                'WhatsApp Gateway tidak dapat dihubungi.',
                previous: $exception
            );
        }

        if (! $response->successful()) {
            throw new WhatsAppDeliveryException(
                'WhatsApp Gateway mengembalikan respons HTTP tidak berhasil.'
            );
        }

        $payload = $response->json();

        if (
            ! is_array($payload)
            || ($payload['status'] ?? null)
                !== true
        ) {
            throw new WhatsAppDeliveryException(
                'WhatsApp Gateway gagal mengirim pesan.'
            );
        }
    }
}
