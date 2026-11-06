<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Consent\IpAnonymizer;
use PHPUnit\Framework\TestCase;

final class IpAnonymizerTest extends TestCase
{
    public function test_ipv4_last_octet_is_zeroed(): void
    {
        self::assertSame('203.0.113.0', IpAnonymizer::mask('203.0.113.42'));
    }

    public function test_ipv6_keeps_only_the_first_64_bits(): void
    {
        self::assertSame('2001:db8::/64', IpAnonymizer::mask('2001:db8::1234:5678'));
    }

    public function test_ipv6_fully_expanded_form_masks_the_same_way(): void
    {
        self::assertSame('2001:db8::/64', IpAnonymizer::mask('2001:0db8:0000:0000:0000:0000:1234:5678'));
    }

    public function test_invalid_input_returns_null(): void
    {
        self::assertNull(IpAnonymizer::mask('not-an-ip'));
        self::assertNull(IpAnonymizer::mask(''));
    }
}
