<?php

declare(strict_types=1);

namespace Contenir\Audience\Exception;

use OutOfBoundsException;

/**
 * No binding has the name asked for.
 *
 * @api
 */
final class UnknownBinding extends OutOfBoundsException
{
    public static function named(string $binding): self
    {
        return new self("There is no audience binding \"{$binding}\"");
    }
}
