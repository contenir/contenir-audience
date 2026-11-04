<?php

declare(strict_types=1);

namespace Contenir\Audience\Storage;

use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Webhook\RegisteredWebhook;
use Contenir\Audience\Webhook\WebhookStoreInterface;
use Override;
use PhpDb\Adapter\AdapterInterface;

/**
 * An add-on's registered webhooks in its "webhook" table, through php-db, on MySQL or SQLite.
 *
 * @api
 */
final readonly class PhpDbWebhookStore implements WebhookStoreInterface
{
    private Database $db;

    private string $table;

    public function __construct(AdapterInterface $adapter, Tables $tables)
    {
        $this->db    = new Database($adapter);
        $this->table = $tables->webhook();
    }

    /**
     * @throws StorageException
     */
    private static function hydrate(Row $row): RegisteredWebhook
    {
        return new RegisteredWebhook(
            $row->string('binding'),
            $row->string('remote_id'),
            $row->nullableString('signing_secret'),
            $row->time('registered_at'),
        );
    }

    #[Override]
    public function all(): array
    {
        $webhooks = [];
        foreach ($this->db->rows($this->db->sql->select($this->table)->order(['binding ASC'])) as $row) {
            $webhook                     = self::hydrate($row);
            $webhooks[$webhook->binding] = $webhook;
        }

        return $webhooks;
    }

    #[Override]
    public function find(string $binding): ?RegisteredWebhook
    {
        $row = $this->db->row($this->db->sql->select($this->table)->where(['binding' => $binding]));

        return null === $row ? null : self::hydrate($row);
    }

    #[Override]
    public function forget(string $binding): void
    {
        $this->db->affectedRows($this->db->sql->delete($this->table)->where(['binding' => $binding]));
    }

    #[Override]
    public function save(RegisteredWebhook $webhook): void
    {
        $this->forget($webhook->binding);
        $this->db->insert($this->db
            ->sql
            ->insert($this->table)
            ->values([
                'binding'        => $webhook->binding,
                'remote_id'      => $webhook->remoteId,
                'signing_secret' => $webhook->signingSecret,
                'registered_at'  => DbTime::format($webhook->registeredAt),
            ]));
    }
}
