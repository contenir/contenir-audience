<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

use Contenir\Audience\Email;
use DateTimeImmutable;

/**
 * One thing a service reported about a person through its webhook.
 *
 * @api
 */
final readonly class RemoteEvent
{
    /**
     * @param Email|null $newEmail Their new address, for an email change
     */
    public function __construct(
        public RemoteEventKind $kind,
        public Email $email,
        public DateTimeImmutable $at,
        public ?Email $newEmail = null,
    ) {}
}
