<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\TestAsset\Schema;

use Contenir\Audience\Schema\AbstractCreateSuppression;
use Contenir\Audience\Tests\TestAsset\Database\TestDatabase;
use Override;

/**
 * The test add-on's suppression table, made the way an add-on subclasses the library's migration.
 *
 * @mago-expect analysis:missing-constructor AbstractMigration sets its adapter and inspector in up() and preview().
 */
final class Version20261105000300CreateSuppression extends AbstractCreateSuppression
{
    #[Override]
    public function getVersion(): string
    {
        return '20261105000300';
    }

    #[Override]
    protected function prefix(): string
    {
        return TestDatabase::PREFIX;
    }
}
