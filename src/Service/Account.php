<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

/**
 * The account a connection reaches, as Test connection shows it.
 *
 * @api
 */
final readonly class Account
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
