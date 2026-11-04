<?php

declare(strict_types=1);

namespace Contenir\Audience\Webhook;

use DateTimeImmutable;
use SensitiveParameter;

/**
 * A webhook registered at the service for one binding's list: the service's id for it, the
 * secret the service signs its calls with when it signs them, and when it was registered.
 *
 * @api
 */
final readonly class RegisteredWebhook
{
    public function __construct(
        public string $binding,
        public string $remoteId,
        #[SensitiveParameter]
        public ?string $signingSecret,
        public DateTimeImmutable $registeredAt,
    ) {}
}
