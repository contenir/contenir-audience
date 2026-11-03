<?php

declare(strict_types=1);

namespace Contenir\Audience;

use DateTimeImmutable;

use function max;
use function min;

/**
 * When a message that couldn't be sent is tried again: after 1 minute, then 5 and 15 minutes,
 * then 1, 4, 12 and 24 hours, then never. A service's Retry-After is honoured when it asks for
 * longer, up to a day.
 *
 * @api
 */
final readonly class Backoff
{
    public const array DELAYS = [60, 300, 900, 3600, 14_400, 43_200, 86_400];

    private const int LONGEST = 86_400;

    /**
     * @param list<positive-int> $delays Seconds before each retry, in order
     */
    public function __construct(
        private array $delays = self::DELAYS,
    ) {}

    /**
     * When to try again after the $attempts-th failure, or null once the retries have run out.
     *
     * @param positive-int $attempts
     */
    public function next(int $attempts, DateTimeImmutable $now, ?int $retryAfter = null): ?DateTimeImmutable
    {
        $base = $this->delays[$attempts - 1] ?? null;
        if (null === $base) {
            return null;
        }

        $delay = min(max($base, $retryAfter ?? $base), self::LONGEST);

        return $now->modify("+{$delay} seconds");
    }
}
