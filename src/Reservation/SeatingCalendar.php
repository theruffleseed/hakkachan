<?php

namespace App\Reservation;

final class SeatingCalendar
{
    public const string START_DATE = '2026-08-07';
    public const int CAPACITY_PAX = 18;
    public const int BOOKING_WEEKS = 4;
    public const int CUTOFF_DAYS = 2;
    private const array SEATING_WEEKDAYS = [5, 6]; // ISO-8601: Friday, Saturday

    /**
     * Seating dates in the booking window from $from: the next BOOKING_WEEKS
     * weeks, no further. Includes nights inside CUTOFF_DAYS — those stay in
     * the dropdown as unselectable. Checkout uses isOpenForBooking() so a
     * greyed-out date cannot be paid for.
     *
     * @return \DateTimeImmutable[]
     */
    public static function upcomingDates(\DateTimeImmutable $from): array
    {
        $start = new \DateTimeImmutable(self::START_DATE);
        $cursor = $from > $start ? $from : $start;
        $until = $from->modify(sprintf('+%d weeks', self::BOOKING_WEEKS));

        $dates = [];
        while ($cursor <= $until) {
            if (\in_array((int) $cursor->format('N'), self::SEATING_WEEKDAYS, true)) {
                $dates[] = $cursor;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $dates;
    }

    public static function isOpenForBooking(\DateTimeImmutable $date, \DateTimeImmutable $from): bool
    {
        return $date >= $from->modify(sprintf('+%d days', self::CUTOFF_DAYS + 1));
    }
}
