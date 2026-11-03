<?php

declare(strict_types=1);

namespace Contenir\Audience\Outbox;

use Contenir\Audience\Consent;
use Contenir\Audience\Email;
use DateTimeImmutable;

/**
 * A message in the outbox, as stored. Its email and values are gone once it's done.
 *
 * @api
 */
final readonly class OutboxRow
{
    /**
     * @param array<string, string|int|float|bool|null> $values
     * @param non-negative-int                          $attempts
     *
     * @mago-expect lint:excessive-parameter-list One parameter per column the outbox keeps.
     */
    public function __construct(
        public int $id,
        public string $binding,
        public Action $action,
        public ?Email $email,
        public string $emailHash,
        public array $values,
        public ?Consent $consent,
        public OutboxState $state,
        public int $attempts,
        public DateTimeImmutable $nextAttemptAt,
        public ?string $lastError,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
