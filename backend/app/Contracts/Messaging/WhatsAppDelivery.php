<?php

namespace App\Contracts\Messaging;

interface WhatsAppDelivery
{
    public function send(
        string $number,
        string $message
    ): void;
}
