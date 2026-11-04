<?php

declare(strict_types=1);

namespace Contenir\Audience\Testing;

use Contenir\Audience\Outbox\Message;
use Contenir\Audience\Outbox\OutboxRow;
use Contenir\Audience\Outbox\OutboxState;
use Contenir\Audience\Outbox\OutboxStoreInterface;
use DateTimeImmutable;
use LogicException;
use Override;

use function array_filter;
use function array_slice;
use function count;
use function usort;

/**
 * An outbox held in memory, for tests: what the php-db store does, without a database.
 *
 * @api
 *
 * @mago-expect lint:too-many-methods One method per operation of its interface, plus the change they share.
 */
final class InMemoryOutboxStore implements OutboxStoreInterface
{
    /** @var array<int, OutboxRow> */
    public array $rows = [];

    private int $next = 1;

    #[Override]
    public function add(Message $message, string $emailHash, DateTimeImmutable $now): int
    {
        $id              = $this->next++;
        $this->rows[$id] = new OutboxRow(
            $id,
            $message->binding,
            $message->action,
            $message->email,
            $emailHash,
            $message->values,
            $message->consent,
            OutboxState::Waiting,
            attempts: 0,
            nextAttemptAt: $now,
            lastError: null,
            createdAt: $now,
            updatedAt: $now,
        );

        return $id;
    }

    #[Override]
    public function counts(): array
    {
        $counts = [];
        foreach (OutboxState::cases() as $state) {
            $counts[$state->value] = count(array_filter(
                $this->rows,
                static fn(OutboxRow $row): bool => $state === $row->state,
            ));
        }

        return $counts;
    }

    #[Override]
    public function dead(int $id, string $reason, DateTimeImmutable $now): void
    {
        $this->change($id, $now, state: OutboxState::Dead, reason: $reason);
    }

    #[Override]
    public function delete(int $id): void
    {
        unset($this->rows[$id]);
    }

    #[Override]
    public function done(int $id, DateTimeImmutable $now): void
    {
        $row             = $this->row($id);
        $this->rows[$id] = new OutboxRow(
            $row->id,
            $row->binding,
            $row->action,
            null,
            $row->emailHash,
            [],
            null,
            OutboxState::Done,
            $row->attempts,
            $row->nextAttemptAt,
            null,
            $row->createdAt,
            $now,
        );
    }

    #[Override]
    public function due(int $limit, DateTimeImmutable $now): array
    {
        $due = array_filter(
            $this->rows,
            static fn(OutboxRow $row): bool => OutboxState::Waiting === $row->state && $row->nextAttemptAt <= $now,
        );
        usort(
            $due,
            static fn(OutboxRow $a, OutboxRow $b): int => [$a->nextAttemptAt, $a->id] <=> [$b->nextAttemptAt, $b->id],
        );

        return array_slice($due, offset: 0, length: $limit);
    }

    #[Override]
    public function fail(int $id, string $reason, DateTimeImmutable $now): void
    {
        $this->change($id, $now, state: OutboxState::Failed, reason: $reason);
    }

    #[Override]
    public function find(int $id): ?OutboxRow
    {
        return $this->rows[$id] ?? null;
    }

    #[Override]
    public function inState(OutboxState $state, int $limit): array
    {
        $rows = array_filter($this->rows, static fn(OutboxRow $row): bool => $state === $row->state);
        usort($rows, static fn(OutboxRow $a, OutboxRow $b): int => [$b->updatedAt, $b->id] <=> [$a->updatedAt, $a->id]);

        return array_slice($rows, offset: 0, length: $limit);
    }

    #[Override]
    public function prune(DateTimeImmutable $doneBefore, DateTimeImmutable $deadBefore): int
    {
        $before     = count($this->rows);
        $this->rows = array_filter($this->rows, static fn(OutboxRow $row): bool => match ($row->state) {
            OutboxState::Done                      => $row->updatedAt >= $doneBefore,
            OutboxState::Failed, OutboxState::Dead => $row->updatedAt >= $deadBefore,
            OutboxState::Waiting                   => true,
        });

        return $before - count($this->rows);
    }

    #[Override]
    public function requeue(int $id, DateTimeImmutable $now): void
    {
        $this->change($id, $now, state: OutboxState::Waiting, attempts: 0, next: $now);
    }

    #[Override]
    public function retry(int $id, DateTimeImmutable $next, string $reason, DateTimeImmutable $now): void
    {
        $row = $this->row($id);
        $this->change($id, $now, attempts: $row->attempts + 1, next: $next, reason: $reason);
    }

    /**
     * The row with $id, as it now stands.
     */
    public function row(int $id): OutboxRow
    {
        return $this->rows[$id] ?? throw new LogicException("No outbox row {$id}");
    }

    /**
     * @param non-negative-int|null $attempts
     *
     * @mago-expect lint:excessive-parameter-list One parameter per thing a change may set, each left as it was when null.
     */
    private function change(
        int $id,
        DateTimeImmutable $now,
        ?OutboxState $state = null,
        ?int $attempts = null,
        ?DateTimeImmutable $next = null,
        ?string $reason = null,
    ): void {
        $row             = $this->row($id);
        $this->rows[$id] = new OutboxRow(
            $row->id,
            $row->binding,
            $row->action,
            $row->email,
            $row->emailHash,
            $row->values,
            $row->consent,
            $state ?? $row->state,
            $attempts ?? $row->attempts,
            $next ?? $row->nextAttemptAt,
            $reason,
            $row->createdAt,
            $now,
        );
    }
}
