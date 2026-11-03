<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Integration\Storage;

use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Storage\PhpDbSubjectStore;
use Contenir\Audience\Subject\Subject;
use Contenir\Audience\Subject\SubjectStoreInterface;
use Contenir\Audience\Tests\TestAsset\Contract\AbstractSubjectStoreContract;
use Contenir\Audience\Tests\TestAsset\Database\TestDatabase;
use Contenir\Audience\Tests\TestAsset\Factory\Make;
use Contenir\Audience\Tests\Trait\TestDatabaseTrait;
use Override;
use PhpDb\Adapter\Adapter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('integration')]
final class PhpDbSubjectStoreTest extends AbstractSubjectStoreContract
{
    use TestDatabaseTrait;

    #[Test]
    public function refusesAStoredFlagThatIsNeitherYesNorNo(): void
    {
        $store = $this->store();
        $store->save(new Subject('newsletter', 'hash-1', 'r1', 'v', true, Make::time()));
        $this->adapter->query(
            'UPDATE ' . TestDatabase::tables()->subject() . ' SET subscribed = 2',
            Adapter::QUERY_MODE_EXECUTE,
        );

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('The audience column "subscribed" does not hold yes or no');

        $store->find('newsletter', 'hash-1');
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    #[Override]
    protected function store(): SubjectStoreInterface
    {
        return new PhpDbSubjectStore($this->adapter, TestDatabase::tables());
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }
}
