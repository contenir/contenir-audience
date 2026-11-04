<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\TestAsset\Schema;

use Contenir\Audience\Schema\AbstractCreateWebhook;
use Contenir\Audience\Tests\TestAsset\Database\TestDatabase;
use Override;

/**
 * The test add-on's webhook table, made the way an add-on subclasses the library's migration.
 *
 * @mago-expect analysis:missing-constructor AbstractMigration sets its adapter and inspector in up() and preview().
 */
final class Version20261105000500CreateWebhook extends AbstractCreateWebhook
{
    #[Override]
    public function getVersion(): string
    {
        return '20261105000500';
    }

    #[Override]
    protected function prefix(): string
    {
        return TestDatabase::PREFIX;
    }
}
