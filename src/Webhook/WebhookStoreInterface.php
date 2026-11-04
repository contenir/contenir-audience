<?php

declare(strict_types=1);

namespace Contenir\Audience\Webhook;

use Contenir\Audience\Exception\StorageException;

/**
 * The webhooks an add-on registered at its service, one for each binding at most.
 *
 * @api
 */
interface WebhookStoreInterface
{
    /**
     * Every registered webhook, by binding name.
     *
     * @return array<string, RegisteredWebhook>
     *
     * @throws StorageException
     */
    public function all(): array;

    /**
     * The binding's webhook, or null when none is registered.
     *
     * @throws StorageException
     */
    public function find(string $binding): ?RegisteredWebhook;

    /**
     * Forgets the binding's webhook, as when the service no longer has it.
     *
     * @throws StorageException
     */
    public function forget(string $binding): void;

    /**
     * Keeps a webhook, replacing any the binding had.
     *
     * @throws StorageException
     */
    public function save(RegisteredWebhook $webhook): void;
}
