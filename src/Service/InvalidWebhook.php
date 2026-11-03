<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

use RuntimeException;

/**
 * A call to a webhook couldn't be verified as the service's, or wasn't for the binding's list.
 * It is refused without changing anything.
 *
 * @api
 */
final class InvalidWebhook extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self("The webhook call was refused: {$reason}");
    }
}
