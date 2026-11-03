<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit\Testing;

use Contenir\Audience\Outbox\OutboxStoreInterface;
use Contenir\Audience\Testing\InMemoryOutboxStore;
use Contenir\Audience\Tests\TestAsset\Contract\AbstractOutboxStoreContract;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
final class InMemoryOutboxStoreTest extends AbstractOutboxStoreContract
{
    #[Test]
    public function refusesToGiveARowThatIsNotThere(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No outbox row 7');

        (new InMemoryOutboxStore())->row(7);
    }

    #[Override]
    protected function store(): OutboxStoreInterface
    {
        return new InMemoryOutboxStore();
    }
}
