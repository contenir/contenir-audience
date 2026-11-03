<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

use Contenir\Audience\Consent;
use Contenir\Audience\Email;

/**
 * One person in a request to a service: their email, the values the binding maps for them, and,
 * when they're joining, how and with what consent.
 *
 * @api
 */
final readonly class Person
{
    /**
     * @param array<string, string|int|float|bool|null> $values Keyed by the service's field
     * @param bool                                      $resubscribe Whether they agreed again after unsubscribing, so the service may take them back
     */
    public function __construct(
        public Email $email,
        public array $values = [],
        public PersonStatus $status = PersonStatus::Subscribed,
        public ?Consent $consent = null,
        public bool $resubscribe = false,
    ) {}
}
