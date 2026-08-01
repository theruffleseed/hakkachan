<?php

namespace App\Tests\Reservation;

use App\Reservation\Pricing;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PricingTest extends TestCase
{
    public function testChargesPaxTimesPrice(): void
    {
        self::assertSame(2 * Pricing::PRICE_PER_PAX_CENTS, Pricing::amountCents(2));
        self::assertSame(5 * Pricing::PRICE_PER_PAX_CENTS, Pricing::amountCents(5));
    }

    public function testRejectsBelowMinimumPax(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pricing::amountCents(Pricing::MIN_PAX - 1);
    }
}
