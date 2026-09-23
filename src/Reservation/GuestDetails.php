<?php

namespace App\Reservation;

/**
 * The guest contact details taken on the reservation form. Lengths mirror the
 * column widths on App\Entity\Reservation.
 *
 * Email is mandatory: every paid booking gets a confirmation email, and
 * Stripe sends its receipt there too.
 */
final readonly class GuestDetails
{
    public const MAX_NAME = 255;
    public const MAX_PHONE = 32;
    public const MAX_EMAIL = 255;

    private function __construct(
        public string $name,
        public ?string $phone,
        public string $email,
    ) {
    }

    /**
     * Name and a real email address are required; phone is optional. Returns
     * null when the submitted details aren't usable — the caller turns that
     * into a flash message.
     */
    public static function fromInput(?string $name, ?string $phone, ?string $email): ?self
    {
        $name = trim((string) $name);
        $phone = trim((string) $phone);
        $email = trim((string) $email);

        $valid = $name !== '' && mb_strlen($name) <= self::MAX_NAME
            && mb_strlen($phone) <= self::MAX_PHONE
            && $email !== '' && mb_strlen($email) <= self::MAX_EMAIL && false !== filter_var($email, \FILTER_VALIDATE_EMAIL);

        return $valid ? new self($name, $phone ?: null, $email) : null;
    }
}
