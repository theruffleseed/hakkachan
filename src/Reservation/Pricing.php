<?php

namespace App\Reservation;

use InvalidArgumentException;

final class Pricing
{
    public const int PRICE_PER_PAX_CENTS = 23000; // RM230.00
    public const int MIN_PAX = 2;

    public static function amountCents(int $pax): int
    {
        if ($pax < self::MIN_PAX) {
            throw new InvalidArgumentException(sprintf('Minimum %d pax required.', self::MIN_PAX));
        }

        return $pax * self::PRICE_PER_PAX_CENTS;
    }
}
