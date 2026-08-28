<?php

namespace App\Tests\Reservation;

use App\Reservation\SeatingCalendar;
use PHPUnit\Framework\TestCase;

final class SeatingCalendarTest extends TestCase
{
    public function testOnlyReturnsFridaysAndSaturdays(): void
    {
        $dates = SeatingCalendar::upcomingDates(new \DateTimeImmutable('2026-09-01'));

        self::assertNotEmpty($dates);
        foreach ($dates as $date) {
            self::assertContains($date->format('N'), ['5', '6']);
        }
    }

    public function testDoesNotReturnDatesBeforeStart(): void
    {
        $dates = SeatingCalendar::upcomingDates(new \DateTimeImmutable('2026-07-29'));

        self::assertSame(SeatingCalendar::START_DATE, $dates[0]->format('Y-m-d'));
    }

    public function testStartsFromGivenDateWhenAfterSeasonStart(): void
    {
        $dates = SeatingCalendar::upcomingDates(new \DateTimeImmutable('2026-09-01'));

        self::assertGreaterThanOrEqual(new \DateTimeImmutable('2026-09-01'), $dates[0]);
    }

    public function testStopsFourWeeksOut(): void
    {
        $from = new \DateTimeImmutable('2026-09-01');
        $dates = SeatingCalendar::upcomingDates($from);

        $cutoff = $from->modify('+4 weeks');
        self::assertLessThanOrEqual($cutoff, end($dates));
        // 4 weeks of Fridays and Saturdays, and nothing beyond the cutoff.
        self::assertCount(8, $dates);
    }

    public function testClosesFridayTwoDaysBefore(): void
    {
        $friday = new \DateTimeImmutable('2026-09-04');
        $saturday = new \DateTimeImmutable('2026-09-05');

        self::assertTrue(SeatingCalendar::isOpenForBooking($friday, new \DateTimeImmutable('2026-09-01')));
        self::assertFalse(SeatingCalendar::isOpenForBooking($friday, new \DateTimeImmutable('2026-09-02')));
        self::assertFalse(SeatingCalendar::isOpenForBooking($friday, new \DateTimeImmutable('2026-09-03')));
        self::assertTrue(SeatingCalendar::isOpenForBooking($saturday, new \DateTimeImmutable('2026-09-02')));
        self::assertFalse(SeatingCalendar::isOpenForBooking($saturday, new \DateTimeImmutable('2026-09-03')));

        $fromWednesday = array_map(
            static fn (\DateTimeImmutable $d): string => $d->format('Y-m-d'),
            SeatingCalendar::upcomingDates(new \DateTimeImmutable('2026-09-02')),
        );
        self::assertContains('2026-09-04', $fromWednesday);
        self::assertContains('2026-09-05', $fromWednesday);
    }
}
