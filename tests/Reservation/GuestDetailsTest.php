<?php

namespace App\Tests\Reservation;

use App\Reservation\GuestDetails;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GuestDetailsTest extends TestCase
{
    public function testAcceptsAndTrimsValidDetails(): void
    {
        $guest = GuestDetails::fromInput('  Wei Ling  ', ' +60 12 345 6789 ', ' WEI@example.com ');

        self::assertNotNull($guest);
        self::assertSame('Wei Ling', $guest->name);
        self::assertSame('+60 12 345 6789', $guest->phone);
        self::assertSame('WEI@example.com', $guest->email);
    }

    public function testAcceptsNameOnlyBecausePhoneAndEmailAreOptional(): void
    {
        $guest = GuestDetails::fromInput('Wei Ling', null, null);

        self::assertNotNull($guest);
        self::assertNull($guest->phone);
        self::assertNull($guest->email);
    }

    public function testTreatsBlankPhoneAndEmailAsNotGiven(): void
    {
        $guest = GuestDetails::fromInput('Wei Ling', '   ', '  ');

        self::assertNotNull($guest);
        self::assertNull($guest->phone);
        self::assertNull($guest->email);
    }

    /**
     * @return iterable<string, array{?string, ?string, ?string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'missing name' => [null, '0123456789', 'wei@example.com'];
        yield 'blank name' => ['   ', '0123456789', 'wei@example.com'];
        yield 'malformed email' => ['Wei Ling', '0123456789', 'wei@example'];
        yield 'name too long' => [str_repeat('a', GuestDetails::MAX_NAME + 1), '0123456789', 'wei@example.com'];
        yield 'phone too long' => ['Wei Ling', str_repeat('1', GuestDetails::MAX_PHONE + 1), 'wei@example.com'];
        yield 'email too long' => ['Wei Ling', '0123456789', str_repeat('a', GuestDetails::MAX_EMAIL).'@example.com'];
    }

    #[DataProvider('invalidInputs')]
    public function testRejectsUnusableDetails(?string $name, ?string $phone, ?string $email): void
    {
        self::assertNull(GuestDetails::fromInput($name, $phone, $email));
    }
}
