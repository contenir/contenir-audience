<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit;

use Contenir\Audience\Backoff;
use Contenir\Audience\Tests\TestAsset\Factory\Make;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BackoffTest extends TestCase
{
    /**
     * @return array<string, array{positive-int, ?int, string}>
     */
    public static function delayProvider(): array
    {
        return [
            'a minute after the first'                   => [1, null, '2026-11-05 09:01:00'],
            'five minutes after the second'              => [2, null, '2026-11-05 09:05:00'],
            'fifteen minutes after the third'            => [3, null, '2026-11-05 09:15:00'],
            'an hour after the fourth'                   => [4, null, '2026-11-05 10:00:00'],
            'four hours after the fifth'                 => [5, null, '2026-11-05 13:00:00'],
            'twelve hours after the sixth'               => [6, null, '2026-11-05 21:00:00'],
            'a day after the seventh'                    => [7, null, '2026-11-06 09:00:00'],
            'longer when the service asks'               => [1, 600, '2026-11-05 09:10:00'],
            'never sooner than its own delay'            => [2, 30, '2026-11-05 09:05:00'],
            'never longer than a day, whatever is asked' => [1, 200_000, '2026-11-06 09:00:00'],
        ];
    }

    #[Test]
    public function followsTheDelaysItIsGiven(): void
    {
        static::assertEquals(Make::time('2026-11-05 09:00:10'), (new Backoff([10]))->next(1, Make::time()));
    }

    #[Test]
    public function givesUpOnceTheRetriesRunOut(): void
    {
        static::assertNull((new Backoff())->next(8, Make::time()));
    }

    /**
     * @param positive-int $attempts
     */
    #[DataProvider('delayProvider')]
    #[Test]
    public function waitsLongerAfterEachFailure(int $attempts, ?int $retryAfter, string $expected): void
    {
        static::assertEquals(Make::time($expected), (new Backoff())->next($attempts, Make::time(), $retryAfter));
    }
}
