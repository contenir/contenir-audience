<?php

declare(strict_types=1);

namespace Contenir\Audience\Testing;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * A clock a test moves by hand.
 *
 * @api
 */
final class MutableClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $now = new DateTimeImmutable('2026-11-05 09:00:00 UTC'),
    ) {}

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
