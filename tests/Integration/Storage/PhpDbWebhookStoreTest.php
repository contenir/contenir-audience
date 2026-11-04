<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Integration\Storage;

use Contenir\Audience\Storage\PhpDbWebhookStore;
use Contenir\Audience\Tests\TestAsset\Contract\AbstractWebhookStoreContract;
use Contenir\Audience\Tests\TestAsset\Database\TestDatabase;
use Contenir\Audience\Tests\Trait\TestDatabaseTrait;
use Contenir\Audience\Webhook\WebhookStoreInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
final class PhpDbWebhookStoreTest extends AbstractWebhookStoreContract
{
    use TestDatabaseTrait;

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    #[Override]
    protected function store(): WebhookStoreInterface
    {
        return new PhpDbWebhookStore($this->adapter, TestDatabase::tables());
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }
}
