<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

use RuntimeException;

/**
 * The service refused the connection itself (401 or 403): the key is wrong, revoked or lacks
 * access. Nothing more is sent until the connection is put right.
 *
 * @api
 */
final class ConnectionRefused extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self("The service refused the connection: {$reason}");
    }
}
