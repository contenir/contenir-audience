<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit\Testing;

use Contenir\Audience\Suppression\SuppressionStoreInterface;
use Contenir\Audience\Testing\InMemorySuppressionStore;
use Contenir\Audience\Tests\TestAsset\Contract\AbstractSuppressionStoreContract;
use Override;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class InMemorySuppressionStoreTest extends AbstractSuppressionStoreContract
{
    #[Override]
    protected function store(): SuppressionStoreInterface
    {
        return new InMemorySuppressionStore();
    }
}
