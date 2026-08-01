<?php

namespace App\Tests\Reservation;

use App\Reservation\SeatingCalendar;
use PHPUnit\Framework\TestCase;

final class SeatingCalendarTest extends TestCase
{
    public function testOnlyReturnsFridaysAndSaturdays(): void
    {
        $dates = SeatingCalendar::upcomingDates(new \DateTimeImmutable('2026-07-29'), 8);

        self::assertCount(8, $dates);
        foreach ($dates as $date) {
            self::assertContains($date->format('N'), ['5', '6']);
        }
    }

    public function testDoesNotReturnDatesBeforeStart(): void
    {
        $dates = SeatingCalendar::upcomingDates(new \DateTimeImmutable('2026-07-29'), 1);

        self::assertSame(SeatingCalendar::START_DATE, $dates[0]->format('Y-m-d'));
    }

    public function testStartsFromGivenDateWhenAfterSeasonStart(): void
    {
        $dates = SeatingCalendar::upcomingDates(new \DateTimeImmutable('2026-09-01'), 1);

        self::assertGreaterThanOrEqual(new \DateTimeImmutable('2026-09-01'), $dates[0]);
    }
}
