<?php

declare(strict_types=1);

namespace Contenir\Audience\Outbox;

use Contenir\Audience\Consent;
use Contenir\Audience\Email;

/**
 * What is put in the outbox: one person, for one binding, with the values mapped for its
 * service and the consent that came with them.
 *
 * @api
 */
final readonly class Message
{
    /**
     * @param array<string, string|int|float|bool|null> $values Keyed by the service's field
     */
    public function __construct(
        public string $binding,
        public Action $action,
        public Email $email,
        public array $values = [],
        public ?Consent $consent = null,
    ) {}
}
