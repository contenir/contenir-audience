<?php

declare(strict_types=1);

namespace Contenir\Audience\Storage;

use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Subject\Subject;
use Contenir\Audience\Subject\SubjectStoreInterface;
use Override;
use PhpDb\Adapter\AdapterInterface;

/**
 * Each binding's sync state in an add-on's "subject" table, through php-db, on MySQL or SQLite.
 *
 * @api
 */
final readonly class PhpDbSubjectStore implements SubjectStoreInterface
{
    private Database $db;

    private string $table;

    public function __construct(AdapterInterface $adapter, Tables $tables)
    {
        $this->db    = new Database($adapter);
        $this->table = $tables->subject();
    }

    /**
     * @throws StorageException
     */
    private static function subscribed(Row $row): bool
    {
        return match ($row->int('subscribed')) {
            0       => false,
            1       => true,
            default => throw StorageException::unreadable('subscribed', 'yes or no'),
        };
    }

    #[Override]
    public function find(string $binding, string $emailHash): ?Subject
    {
        $row = $this->db->row($this->db
            ->sql
            ->select($this->table)
            ->where([
                'binding'    => $binding,
                'email_hash' => $emailHash,
            ]));

        return null === $row
            ? null
            : new Subject(
                $row->string('binding'),
                $row->string('email_hash'),
                $row->nullableString('remote_id'),
                $row->string('values_hash'),
                self::subscribed($row),
                $row->time('synced_at'),
            );
    }

    #[Override]
    public function forget(string $binding, string $emailHash): void
    {
        $this->db->affectedRows($this->db
            ->sql
            ->delete($this->table)
            ->where([
                'binding'    => $binding,
                'email_hash' => $emailHash,
            ]));
    }

    #[Override]
    public function save(Subject $subject): void
    {
        $values = [
            'remote_id'   => $subject->remoteId,
            'values_hash' => $subject->valuesHash,
            'subscribed'  => $subject->subscribed ? 1 : 0,
            'synced_at'   => DbTime::format($subject->syncedAt),
        ];
        $this->forget($subject->binding, $subject->emailHash);
        $this->db->insert($this->db
            ->sql
            ->insert($this->table)
            ->values([
                'binding'    => $subject->binding,
                'email_hash' => $subject->emailHash,
                ...$values,
            ]));
    }
}
