<?php

namespace Tests\Unit;

use App\Support\Phone\IndonesianPhoneNumber;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class IndonesianPhoneNumberTest extends TestCase
{
    public function test_it_normalizes_local_zero_prefix(): void
    {
        $this->assertSame(
            '6287739029392',
            IndonesianPhoneNumber::normalize(
                '087739029392'
            )
        );
    }

    public function test_it_normalizes_plus_sixty_two(): void
    {
        $this->assertSame(
            '6287739029392',
            IndonesianPhoneNumber::normalize(
                '+62 877-3902-9392'
            )
        );
    }

    public function test_it_accepts_canonical_number(): void
    {
        $this->assertSame(
            '6287739029392',
            IndonesianPhoneNumber::normalize(
                '6287739029392'
            )
        );
    }

    public function test_it_normalizes_mobile_number_without_zero(): void
    {
        $this->assertSame(
            '6287739029392',
            IndonesianPhoneNumber::normalize(
                '87739029392'
            )
        );
    }

    public function test_empty_value_returns_null(): void
    {
        $this->assertNull(
            IndonesianPhoneNumber::normalize(
                ''
            )
        );
    }

    public function test_invalid_country_format_is_rejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        IndonesianPhoneNumber::normalize(
            '1234567890'
        );
    }

    public function test_it_can_mask_phone_number(): void
    {
        $this->assertSame(
            '6287*****9392',
            IndonesianPhoneNumber::masked(
                '087739029392'
            )
        );
    }
}
