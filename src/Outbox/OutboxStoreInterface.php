<?php

declare(strict_types=1);

namespace Contenir\Audience\Outbox;

use Contenir\Audience\Exception\StorageException;
use DateTimeImmutable;

/**
 * The messages waiting to go to a service, and what became of them.
 *
 * @api
 *
 * @mago-expect lint:too-many-methods What the worker needs, what the Sync screen needs, and pruning.
 */
interface OutboxStoreInterface
{
    /**
     * Puts a message in the outbox, due at once, and answers with its id.
     *
     * @throws StorageException
     */
    public function add(Message $message, string $emailHash, DateTimeImmutable $now): int;

    /**
     * How many messages are in each state, for the Sync screen and the update check.
     *
     * @return array<value-of<OutboxState>, int> Every state, with 0 for none
     *
     * @throws StorageException
     */
    public function counts(): array;

    /**
     * @throws StorageException
     */
    public function dead(int $id, string $reason, DateTimeImmutable $now): void;

    /**
     * Removes a message, as the Sync screen's Remove does.
     *
     * @throws StorageException
     */
    public function delete(int $id): void;

    /**
     * Marks a message done, and clears its email and values.
     *
     * @throws StorageException
     */
    public function done(int $id, DateTimeImmutable $now): void;

    /**
     * The waiting messages due by $now, oldest first.
     *
     * @param positive-int $limit
     *
     * @return list<OutboxRow>
     *
     * @throws StorageException
     */
    public function due(int $limit, DateTimeImmutable $now): array;

    /**
     * @throws StorageException
     */
    public function fail(int $id, string $reason, DateTimeImmutable $now): void;

    /**
     * The message with $id, as it now stands, or null when there's none.
     *
     * @throws StorageException
     */
    public function find(int $id): ?OutboxRow;

    /**
     * Messages in $state, most recently changed first, for the Sync screen.
     *
     * @param positive-int $limit
     *
     * @return list<OutboxRow>
     *
     * @throws StorageException
     */
    public function inState(OutboxState $state, int $limit): array;

    /**
     * Deletes done messages last changed before $doneBefore, and failed or dead ones before
     * $deadBefore, and answers with how many went.
     *
     * @throws StorageException
     */
    public function prune(DateTimeImmutable $doneBefore, DateTimeImmutable $deadBefore): int;

    /**
     * Puts a failed or dead message back to wait, due at once with a full set of retries, as the
     * Sync screen's Retry does.
     *
     * @throws StorageException
     */
    public function requeue(int $id, DateTimeImmutable $now): void;

    /**
     * Counts one more attempt, and leaves the message waiting until $next.
     *
     * @throws StorageException
     */
    public function retry(int $id, DateTimeImmutable $next, string $reason, DateTimeImmutable $now): void;
}
