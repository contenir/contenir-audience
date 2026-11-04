<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit\Testing;

use Contenir\Audience\Subject\SubjectStoreInterface;
use Contenir\Audience\Testing\InMemorySubjectStore;
use Contenir\Audience\Tests\TestAsset\Contract\AbstractSubjectStoreContract;
use Override;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class InMemorySubjectStoreTest extends AbstractSubjectStoreContract
{
    #[Override]
    protected function store(): SubjectStoreInterface
    {
        return new InMemorySubjectStore();
    }
}
