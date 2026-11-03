<?php

declare(strict_types=1);

namespace Contenir\Audience\Storage;

use Contenir\Audience\Email;
use Contenir\Audience\Exception\InvalidEmail;
use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Outbox\Action;
use Contenir\Audience\Outbox\Message;
use Contenir\Audience\Outbox\OutboxRow;
use Contenir\Audience\Outbox\OutboxState;
use Contenir\Audience\Outbox\OutboxStoreInterface;
use DateTimeImmutable;
use Override;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Expression;
use PhpDb\Sql\Where;

use function array_map;

/**
 * An add-on's outbox in its "outbox" table, through php-db, on MySQL or SQLite.
 *
 * @api
 *
 * @mago-expect lint:too-many-methods One method per operation of its interface, plus the update, delete and hydration they share.
 */
final readonly class PhpDbOutboxStore implements OutboxStoreInterface
{
    private Database $db;

    private string $table;

    public function __construct(AdapterInterface $adapter, Tables $tables)
    {
        $this->db    = new Database($adapter);
        $this->table = $tables->outbox();
    }

    /**
     * @return non-negative-int
     *
     * @throws StorageException
     */
    private static function attempts(Row $row): int
    {
        $attempts = $row->int('attempts');

        return 0 <= $attempts ? $attempts : throw StorageException::unreadable('attempts', 'a count of attempts');
    }

    /**
     * @throws StorageException
     */
    private static function hydrate(Row $row): OutboxRow
    {
        $email = $row->nullableString('email');
        try {
            return new OutboxRow(
                id: $row->int('id'),
                binding: $row->string('binding'),
                action: Action::tryFrom($row->string('action'))
                ?? throw StorageException::unreadable('action', 'an outbox action'),
                email: null === $email ? null : Email::fromString($email),
                emailHash: $row->string('email_hash'),
                values: Json::decodeValues($row->nullableString('payload')),
                consent: Json::decodeConsent($row->nullableString('consent')),
                state: OutboxState::tryFrom($row->string('state'))
                ?? throw StorageException::unreadable('state', 'an outbox state'),
                attempts: self::attempts($row),
                nextAttemptAt: $row->time('next_attempt_at'),
                lastError: $row->nullableString('last_error'),
                createdAt: $row->time('created_at'),
                updatedAt: $row->time('updated_at'),
            );
        } catch (InvalidEmail) {
            throw StorageException::unreadable('email', 'an email address');
        }
    }

    #[Override]
    public function add(Message $message, string $emailHash, DateTimeImmutable $now): int
    {
        $at = DbTime::format($now);

        return $this->db->insert($this->db
            ->sql
            ->insert($this->table)
            ->values([
                'binding'         => $message->binding,
                'action'          => $message->action->value,
                'email'           => $message->email->value,
                'email_hash'      => $emailHash,
                'payload'         => Json::values($message->values),
                'consent'         => Json::consent($message->consent),
                'state'           => OutboxState::Waiting->value,
                'attempts'        => 0,
                'next_attempt_at' => $at,
                'last_error'      => null,
                'created_at'      => $at,
                'updated_at'      => $at,
            ]));
    }

    #[Override]
    public function counts(): array
    {
        $counts = [];
        foreach (OutboxState::cases() as $state) {
            $counts[$state->value] = 0;
        }

        $select = $this->db
            ->sql
            ->select($this->table)
            ->columns(['state', 'total' => new Expression('COUNT(*)')])
            ->group('state');
        foreach ($this->db->rows($select) as $row) {
            $state = OutboxState::tryFrom($row->string('state'));
            if (null !== $state) {
                $counts[$state->value] = $row->int('total');
            }
        }

        return $counts;
    }

    #[Override]
    public function dead(int $id, string $reason, DateTimeImmutable $now): void
    {
        $this->update($id, ['state' => OutboxState::Dead->value, 'last_error' => $reason], $now);
    }

    #[Override]
    public function delete(int $id): void
    {
        $this->db->affectedRows($this->db->sql->delete($this->table)->where(['id' => $id]));
    }

    #[Override]
    public function done(int $id, DateTimeImmutable $now): void
    {
        $this->update(
            $id,
            [
                'state'      => OutboxState::Done->value,
                'email'      => null,
                'payload'    => null,
                'consent'    => null,
                'last_error' => null,
            ],
            $now,
        );
    }

    #[Override]
    public function due(int $limit, DateTimeImmutable $now): array
    {
        $at     = DbTime::format($now);
        $select = $this->db
            ->sql
            ->select($this->table)
            ->where(static function (Where $where) use ($at): void {
                $where->equalTo('state', OutboxState::Waiting->value);
                $where->lessThanOrEqualTo('next_attempt_at', $at);
            })
            ->order(['next_attempt_at ASC', 'id ASC'])
            ->limit($limit);

        $rows = [];
        foreach ($this->db->rows($select) as $row) {
            $rows[] = self::hydrate($row);
        }

        return $rows;
    }

    #[Override]
    public function fail(int $id, string $reason, DateTimeImmutable $now): void
    {
        $this->update($id, ['state' => OutboxState::Failed->value, 'last_error' => $reason], $now);
    }

    #[Override]
    public function find(int $id): ?OutboxRow
    {
        $row = $this->db->row($this->db->sql->select($this->table)->where(['id' => $id]));

        return null === $row ? null : self::hydrate($row);
    }

    #[Override]
    public function inState(OutboxState $state, int $limit): array
    {
        $select = $this->db
            ->sql
            ->select($this->table)
            ->where(['state' => $state->value])
            ->order(['updated_at DESC', 'id DESC'])
            ->limit($limit);

        $rows = [];
        foreach ($this->db->rows($select) as $row) {
            $rows[] = self::hydrate($row);
        }

        return $rows;
    }

    #[Override]
    public function prune(DateTimeImmutable $doneBefore, DateTimeImmutable $deadBefore): int
    {
        return (
            $this->deleteBefore([OutboxState::Done], $doneBefore)
                + $this->deleteBefore([OutboxState::Failed, OutboxState::Dead], $deadBefore)
        );
    }

    #[Override]
    public function requeue(int $id, DateTimeImmutable $now): void
    {
        $this->update(
            $id,
            [
                'state'           => OutboxState::Waiting->value,
                'attempts'        => 0,
                'next_attempt_at' => DbTime::format($now),
                'last_error'      => null,
            ],
            $now,
        );
    }

    #[Override]
    public function retry(int $id, DateTimeImmutable $next, string $reason, DateTimeImmutable $now): void
    {
        $this->update(
            $id,
            [
                'attempts'        => new Expression('attempts + 1'),
                'next_attempt_at' => DbTime::format($next),
                'last_error'      => $reason,
            ],
            $now,
        );
    }

    /**
     * Deletes the messages in one of $states last changed before $before.
     *
     * @param non-empty-list<OutboxState> $states
     *
     * @throws StorageException
     */
    private function deleteBefore(array $states, DateTimeImmutable $before): int
    {
        $at    = DbTime::format($before);
        $names = array_map(static fn(OutboxState $state): string => $state->value, $states);

        return $this->db->affectedRows($this->db
            ->sql
            ->delete($this->table)
            ->where(
                static function (Where $where) use ($names, $at): void {
                    $where->in('state', $names);
                    $where->lessThan('updated_at', $at);
                },
            ));
    }

    /**
     * @param array<string, mixed> $set
     *
     * @throws StorageException
     */
    private function update(int $id, array $set, DateTimeImmutable $now): void
    {
        $this->db->affectedRows($this->db
            ->sql
            ->update($this->table)
            ->set([
                ...$set,
                'updated_at' => DbTime::format($now),
            ])
            ->where(['id' => $id]));
    }
}
