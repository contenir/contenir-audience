<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit\Testing;

use Contenir\Audience\Testing\InMemoryWebhookStore;
use Contenir\Audience\Tests\TestAsset\Contract\AbstractWebhookStoreContract;
use Contenir\Audience\Webhook\WebhookStoreInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class InMemoryWebhookStoreTest extends AbstractWebhookStoreContract
{
    #[Override]
    protected function store(): WebhookStoreInterface
    {
        return new InMemoryWebhookStore();
    }
}
