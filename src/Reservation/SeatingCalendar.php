<?php

namespace App\Reservation;

final class SeatingCalendar
{
    public const string START_DATE = '2026-08-07';
    public const int CAPACITY_PAX = 18;
    private const array SEATING_WEEKDAYS = [5, 6]; // ISO-8601: Friday, Saturday

    /**
     * @return \DateTimeImmutable[]
     */
    public static function upcomingDates(\DateTimeImmutable $from, int $count): array
    {
        $start = new \DateTimeImmutable(self::START_DATE);
        $cursor = $from > $start ? $from : $start;

        $dates = [];
        while (count($dates) < $count) {
            if (\in_array((int) $cursor->format('N'), self::SEATING_WEEKDAYS, true)) {
                $dates[] = $cursor;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $dates;
    }
}
