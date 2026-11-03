<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Integration\Storage;

use Contenir\Audience\Storage\PhpDbSuppressionStore;
use Contenir\Audience\Suppression\SuppressionStoreInterface;
use Contenir\Audience\Tests\TestAsset\Contract\AbstractSuppressionStoreContract;
use Contenir\Audience\Tests\TestAsset\Database\TestDatabase;
use Contenir\Audience\Tests\Trait\TestDatabaseTrait;
use Override;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
final class PhpDbSuppressionStoreTest extends AbstractSuppressionStoreContract
{
    use TestDatabaseTrait;

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    #[Override]
    protected function store(): SuppressionStoreInterface
    {
        return new PhpDbSuppressionStore($this->adapter, TestDatabase::tables());
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }
}
