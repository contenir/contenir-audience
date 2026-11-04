<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

/**
 * What became of one person in a request to a service, with the service's id for them when it
 * gave one, or why it didn't do what was asked.
 *
 * @api
 */
final readonly class Outcome
{
    /**
     * @param int|null $retryAfter Seconds the service asked to wait before trying again
     */
    private function __construct(
        public OutcomeKind $kind,
        public ?string $remoteId = null,
        public ?string $reason = null,
        public ?int $retryAfter = null,
    ) {}

    public static function done(?string $remoteId = null): self
    {
        return new self(OutcomeKind::Done, $remoteId);
    }

    public static function failed(string $reason): self
    {
        return new self(OutcomeKind::Failed, reason: $reason);
    }

    public static function forgotten(string $reason): self
    {
        return new self(OutcomeKind::Forgotten, reason: $reason);
    }

    public static function retry(string $reason, ?int $retryAfter = null): self
    {
        return new self(OutcomeKind::Retry, reason: $reason, retryAfter: $retryAfter);
    }
}
