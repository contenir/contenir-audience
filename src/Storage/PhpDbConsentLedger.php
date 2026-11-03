<?php

declare(strict_types=1);

namespace Contenir\Audience\Storage;

use Contenir\Audience\Consent;
use Contenir\Audience\ConsentLedgerInterface;
use Override;
use PhpDb\Adapter\AdapterInterface;

/**
 * Each binding's consent ledger in an add-on's "consent" table, through php-db, on MySQL or
 * SQLite.
 *
 * @api
 */
final readonly class PhpDbConsentLedger implements ConsentLedgerInterface
{
    private Database $db;

    private string $table;

    public function __construct(AdapterInterface $adapter, Tables $tables)
    {
        $this->db    = new Database($adapter);
        $this->table = $tables->consent();
    }

    #[Override]
    public function record(string $binding, string $emailHash, Consent $consent): void
    {
        $this->db->insert($this->db
            ->sql
            ->insert($this->table)
            ->values([
                'binding'    => $binding,
                'email_hash' => $emailHash,
                'wording'    => $consent->wording,
                'source'     => $consent->source->value,
                'ip'         => $consent->ip,
                'agreed_at'  => DbTime::format($consent->at),
            ]));
    }
}
