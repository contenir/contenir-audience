<?php

declare(strict_types=1);

namespace Contenir\Audience\Testing;

use Contenir\Audience\Webhook\RegisteredWebhook;
use Contenir\Audience\Webhook\WebhookStoreInterface;
use Override;

use function ksort;

/**
 * Registered webhooks held in memory, for tests.
 *
 * @api
 */
final class InMemoryWebhookStore implements WebhookStoreInterface
{
    /** @var array<string, RegisteredWebhook> */
    public array $webhooks = [];

    #[Override]
    public function all(): array
    {
        $webhooks = $this->webhooks;
        ksort($webhooks);

        return $webhooks;
    }

    #[Override]
    public function find(string $binding): ?RegisteredWebhook
    {
        return $this->webhooks[$binding] ?? null;
    }

    #[Override]
    public function forget(string $binding): void
    {
        unset($this->webhooks[$binding]);
    }

    #[Override]
    public function save(RegisteredWebhook $webhook): void
    {
        $this->webhooks[$webhook->binding] = $webhook;
    }
}
