<?php

declare(strict_types=1);

namespace Contenir\Audience\Subject;

use Contenir\Audience\Exception\StorageException;

/**
 * The people each binding has sent to its service.
 *
 * @api
 */
interface SubjectStoreInterface
{
    /**
     * @throws StorageException
     */
    public function find(string $binding, string $emailHash): ?Subject;

    /**
     * @throws StorageException
     */
    public function forget(string $binding, string $emailHash): void;

    /**
     * @throws StorageException
     */
    public function save(Subject $subject): void;
}
