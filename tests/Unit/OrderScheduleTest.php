<?php

namespace App\Tests\Unit;

use App\Service\OrderSchedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

/**
 * The shop takes its last same-day order at 5:00 PM Manila time. The browser's date picker
 * and the server both read these rules, so they must not drift apart.
 */
final class OrderScheduleTest extends TestCase
{
    protected function tearDown(): void
    {
        Clock::set(new \Symfony\Component\Clock\NativeClock());
    }

    private function scheduleAt(string $manilaTime): OrderSchedule
    {
        Clock::set(new MockClock(new \DateTimeImmutable($manilaTime, new \DateTimeZone(OrderSchedule::TIMEZONE))));

        return new OrderSchedule();
    }

    public function testBeforeTheCutoffTodayIsStillOrderable(): void
    {
        $schedule = $this->scheduleAt('2026-06-15 16:59');

        self::assertSame('2026-06-15', $schedule->earliestDate());
        self::assertTrue($schedule->isOrderable(new \DateTimeImmutable('2026-06-15')));
    }

    public function testAtTheCutoffTodayIsClosed(): void
    {
        $schedule = $this->scheduleAt('2026-06-15 17:00');

        self::assertSame('2026-06-16', $schedule->earliestDate());
        self::assertFalse($schedule->isOrderable(new \DateTimeImmutable('2026-06-15')));
        self::assertTrue($schedule->isOrderable(new \DateTimeImmutable('2026-06-16')));
    }

    public function testPastDatesAreNeverOrderable(): void
    {
        $schedule = $this->scheduleAt('2026-06-15 10:00');

        self::assertFalse($schedule->isOrderable(new \DateTimeImmutable('2026-06-14')));
    }

    public function testFutureDatesAreAlwaysOrderable(): void
    {
        $schedule = $this->scheduleAt('2026-06-15 23:30');

        self::assertTrue($schedule->isOrderable(new \DateTimeImmutable('2026-12-24')));
    }

    public function testTheCutoffRollsOverTheMonth(): void
    {
        $schedule = $this->scheduleAt('2026-06-30 18:00');

        self::assertSame('2026-07-01', $schedule->earliestDate());
    }
}
