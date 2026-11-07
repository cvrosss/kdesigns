<?php

namespace App\Service;

use Symfony\Component\Clock\Clock;

/**
 * When orders may be placed for. The shop closes at 6:00 PM but takes its last order for the same day
 * at 5:00 PM (Asia/Manila), leaving an hour to prepare. The browser reads these same values from the
 * order modal, so the date picker and the server never disagree.
 */
final class OrderSchedule
{
    public const TIMEZONE = 'Asia/Manila';

    /** Same-day orders are refused from this time (24h "H:i") onward. */
    public const SAME_DAY_CUTOFF = '17:00';

    /** The current moment in the shop's timezone. */
    public function now(): \DateTimeImmutable
    {
        return Clock::get()->now()->setTimezone(new \DateTimeZone(self::TIMEZONE));
    }

    /** Today's calendar date in the shop's timezone, as Y-m-d. */
    public function today(): string
    {
        return $this->now()->format('Y-m-d');
    }

    /** The earliest date (Y-m-d) an order can be placed for: today before the cutoff, otherwise tomorrow. */
    public function earliestDate(): string
    {
        $now = $this->now();

        return $now->format('H:i') >= self::SAME_DAY_CUTOFF
            ? $now->modify('+1 day')->format('Y-m-d')
            : $now->format('Y-m-d');
    }

    /** True when the plain calendar $date is still open for ordering. */
    public function isOrderable(\DateTimeImmutable $date): bool
    {
        return $date->format('Y-m-d') >= $this->earliestDate();
    }
}
