<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

/**
 * A list (Mailchimp's audience) at the service, as a binding names it.
 *
 * @api
 */
final readonly class RemoteList
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
