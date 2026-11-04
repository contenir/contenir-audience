<?php

declare(strict_types=1);

namespace Contenir\Audience;

use Contenir\Audience\Exception\StorageException;

/**
 * Who agreed to be on each binding's list, to what wording, when and from where, kept by a hash
 * of their email.
 *
 * @api
 */
interface ConsentLedgerInterface
{
    /**
     * @throws StorageException
     */
    public function record(string $binding, string $emailHash, Consent $consent): void;
}
