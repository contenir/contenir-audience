<?php

declare(strict_types=1);

namespace Contenir\Audience\Exception;

use InvalidArgumentException;

/**
 * A binding in the configuration can't be used as written.
 *
 * @api
 */
final class InvalidBinding extends InvalidArgumentException
{
    public static function because(string $binding, string $problem): self
    {
        return new self("The audience binding \"{$binding}\" {$problem}");
    }
}
