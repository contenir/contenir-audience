<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

use SensitiveParameter;

/**
 * A webhook a service registered for a list: its id there, and the secret it signs each call
 * with, when it signs them. Mailchimp gives the secret once, as it registers the webhook, so it
 * must be kept then or the webhook registered again.
 *
 * @api
 */
final readonly class WebhookRegistration
{
    public function __construct(
        public string $id,
        #[SensitiveParameter]
        public ?string $signingSecret = null,
    ) {}
}
