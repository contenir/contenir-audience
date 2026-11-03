<?php

declare(strict_types=1);

namespace Contenir\Audience\Storage;

use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Suppression\SuppressionReason;
use Contenir\Audience\Suppression\SuppressionStoreInterface;
use DateTimeImmutable;
use Override;
use PhpDb\Adapter\AdapterInterface;

/**
 * Each binding's suppressions in an add-on's "suppression" table, through php-db, on MySQL or
 * SQLite.
 *
 * @api
 */
final readonly class PhpDbSuppressionStore implements SuppressionStoreInterface
{
    private Database $db;

    private string $table;

    public function __construct(AdapterInterface $adapter, Tables $tables)
    {
        $this->db    = new Database($adapter);
        $this->table = $tables->suppression();
    }

    #[Override]
    public function lift(string $binding, string $emailHash): void
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
    public function reason(string $binding, string $emailHash): ?SuppressionReason
    {
        $row = $this->db->row($this->db
            ->sql
            ->select($this->table)
            ->columns(['reason'])
            ->where([
                'binding'    => $binding,
                'email_hash' => $emailHash,
            ]));
        if (null === $row) {
            return null;
        }

        return (
            SuppressionReason::tryFrom($row->string('reason'))
                ?? throw StorageException::unreadable('reason', 'a suppression reason')
        );
    }

    #[Override]
    public function suppress(string $binding, string $emailHash, SuppressionReason $reason, DateTimeImmutable $at): void
    {
        $this->lift($binding, $emailHash);
        $this->db->insert($this->db
            ->sql
            ->insert($this->table)
            ->values([
                'binding'       => $binding,
                'email_hash'    => $emailHash,
                'reason'        => $reason->value,
                'suppressed_at' => DbTime::format($at),
            ]));
    }
}
