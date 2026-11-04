<?php

declare(strict_types=1);

namespace Contenir\Audience\Subject;

use DateTimeImmutable;

/**
 * What a binding last did for one person at its service, kept by a hash of their email: the
 * service's id for them, a hash of the values last sent, and whether they're subscribed.
 *
 * @api
 */
final readonly class Subject
{
    /**
     * @mago-expect lint:excessive-parameter-list One parameter per column the sync state keeps.
     */
    public function __construct(
        public string $binding,
        public string $emailHash,
        public ?string $remoteId,
        public string $valuesHash,
        public bool $subscribed,
        public DateTimeImmutable $syncedAt,
    ) {}
}
