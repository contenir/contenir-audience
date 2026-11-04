<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Integration\Storage;

use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Outbox\OutboxStoreInterface;
use Contenir\Audience\Storage\PhpDbOutboxStore;
use Contenir\Audience\Tests\TestAsset\Contract\AbstractOutboxStoreContract;
use Contenir\Audience\Tests\TestAsset\Database\TestDatabase;
use Contenir\Audience\Tests\TestAsset\Factory\Make;
use Contenir\Audience\Tests\Trait\TestDatabaseTrait;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

use function current;
use function key;

#[Group('integration')]
final class PhpDbOutboxStoreTest extends AbstractOutboxStoreContract
{
    use TestDatabaseTrait;

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function corruptProvider(): array
    {
        return [
            'an email that is not one' => [
                ['email' => 'not an email'],
                'The audience column "email" does not hold an email address',
            ],
            'an unknown action'        => [
                ['action' => 'shout'],
                'The audience column "action" does not hold an outbox action',
            ],
            'a negative attempt count' => [
                ['attempts' => '-1'],
                'The audience column "attempts" does not hold a count of attempts',
            ],
            'an unknown state'         => [
                ['state' => 'lost'],
                'The audience column "state" does not hold an outbox state',
            ],
        ];
    }

    /**
     * @param array<string, string> $corrupt
     */
    #[DataProvider('corruptProvider')]
    #[Test]
    public function refusesARowItCannotRead(array $corrupt, string $message): void
    {
        if ('attempts' === key($corrupt) && 'MySQL' === $this->adapter->getPlatform()->getName()) {
            static::markTestSkipped('MySQL keeps attempts unsigned, so only SQLite can hold a negative count');
        }

        $store = $this->store();
        $id    = $store->add(Make::message(), 'a', Make::time());
        $this->adapter->query(
            'UPDATE ' . TestDatabase::tables()->outbox() . ' SET ' . key($corrupt) . ' = ? WHERE id = ?',
            [current($corrupt), $id],
        );

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage($message);

        $store->find($id);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    #[Override]
    protected function store(): OutboxStoreInterface
    {
        return new PhpDbOutboxStore($this->adapter, TestDatabase::tables());
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }
}
