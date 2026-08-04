<?php

namespace App\Reservation;

final class SeatingCalendar
{
    public const string START_DATE = '2026-08-07';
    public const int CAPACITY_PAX = 18;
    public const int BOOKING_WEEKS = 4;
    private const array SEATING_WEEKDAYS = [5, 6]; // ISO-8601: Friday, Saturday

    /**
     * Every seating date bookable from $from: the next BOOKING_WEEKS weeks, no
     * further. One horizon for both the date list and checkout validation, so a
     * date the form won't offer is a date checkout won't take.
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
}
