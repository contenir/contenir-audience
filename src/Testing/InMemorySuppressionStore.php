<?php

declare(strict_types=1);

namespace Contenir\Audience\Testing;

use Contenir\Audience\Suppression\SuppressionReason;
use Contenir\Audience\Suppression\SuppressionStoreInterface;
use DateTimeImmutable;
use Override;

/**
 * Suppressions held in memory, for tests.
 *
 * @api
 */
final class InMemorySuppressionStore implements SuppressionStoreInterface
{
    /** @var array<string, SuppressionReason> */
    public array $reasons = [];

    #[Override]
    public function lift(string $binding, string $emailHash): void
    {
        unset($this->reasons["{$binding}\0{$emailHash}"]);
    }

    #[Override]
    public function reason(string $binding, string $emailHash): ?SuppressionReason
    {
        return $this->reasons["{$binding}\0{$emailHash}"] ?? null;
    }

    #[Override]
    public function suppress(string $binding, string $emailHash, SuppressionReason $reason, DateTimeImmutable $at): void
    {
        $this->reasons["{$binding}\0{$emailHash}"] = $reason;
    }
}
