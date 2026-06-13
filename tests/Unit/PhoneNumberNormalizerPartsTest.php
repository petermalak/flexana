<?php

namespace Tests\Unit;

use App\Support\PhoneNumberNormalizer;
use Tests\TestCase;

class PhoneNumberNormalizerPartsTest extends TestCase
{
    public function test_from_parts_egyptian_number(): void
    {
        $parts = PhoneNumberNormalizer::fromParts('20', '01012345678');

        $this->assertNotNull($parts);
        $this->assertSame('+201012345678', $parts['e164']);
        $this->assertSame('20', $parts['countryCode']);
        $this->assertSame('1012345678', $parts['phoneNumber']);
    }

    public function test_from_parts_foreign_number(): void
    {
        $parts = PhoneNumberNormalizer::fromParts('+44', '07911123456');

        $this->assertNotNull($parts);
        $this->assertSame('+447911123456', $parts['e164']);
        $this->assertSame('44', $parts['countryCode']);
        $this->assertSame('7911123456', $parts['phoneNumber']);
    }

    public function test_parts_from_e164_default_country(): void
    {
        $parts = PhoneNumberNormalizer::partsFromE164('+201012345678');

        $this->assertNotNull($parts);
        $this->assertSame('20', $parts['countryCode']);
        $this->assertSame('1012345678', $parts['phoneNumber']);
    }
}
