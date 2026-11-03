<?php

declare(strict_types=1);

namespace Contenir\Audience\Exception;

use InvalidArgumentException;

/**
 * What was given as an email address isn't one.
 *
 * @api
 */
final class InvalidEmail extends InvalidArgumentException
{
    public static function of(string $address): self
    {
        return new self("\"{$address}\" is not an email address");
    }
}
