<?php

declare(strict_types=1);

namespace Contenir\Audience\Suppression;

use Contenir\Audience\Exception\StorageException;
use DateTimeImmutable;

/**
 * The people each binding must not subscribe, by a hash of their email. Kept indefinitely: it's
 * what stops a re-subscribe.
 *
 * @api
 */
interface SuppressionStoreInterface
{
    /**
     * Lifts a suppression once the person has agreed again and been taken back.
     *
     * @throws StorageException
     */
    public function lift(string $binding, string $emailHash): void;

    /**
     * @throws StorageException
     */
    public function reason(string $binding, string $emailHash): ?SuppressionReason;

    /**
     * @throws StorageException
     */
    public function suppress(
        string $binding,
        string $emailHash,
        SuppressionReason $reason,
        DateTimeImmutable $at,
    ): void;
}
