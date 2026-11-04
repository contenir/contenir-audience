<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Integration\Schema;

use Contenir\Audience\Tests\TestAsset\Database\TestDatabase;
use Contenir\Audience\Tests\Trait\TestDatabaseTrait;
use Override;
use PhpDb\Migration\SchemaInspector;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_values;
use function sort;

#[Group('integration')]
final class MigrationsTest extends TestCase
{
    use TestDatabaseTrait;

    #[Test]
    public function createsEachTableUnderTheAddOnsPrefix(): void
    {
        $tables = TestDatabase::tables();

        static::assertSame(
            [
                [
                    'action',
                    'attempts',
                    'binding',
                    'consent',
                    'created_at',
                    'email',
                    'email_hash',
                    'id',
                    'last_error',
                    'next_attempt_at',
                    'payload',
                    'state',
                    'updated_at',
                ],
                ['binding', 'email_hash', 'remote_id', 'subscribed', 'synced_at', 'values_hash'],
                ['binding', 'email_hash', 'reason', 'suppressed_at'],
                ['agreed_at', 'binding', 'email_hash', 'id', 'ip', 'source', 'wording'],
            ],
            [
                $this->columns($tables->outbox()),
                $this->columns($tables->subject()),
                $this->columns($tables->suppression()),
                $this->columns($tables->consent()),
            ],
        );
    }

    #[Test]
    public function runsNothingTheSecondTime(): void
    {
        static::assertSame([], TestDatabase::migrations($this->adapter)->runPending());
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    /**
     * @return list<string>
     */
    private function columns(string $table): array
    {
        $names = array_map(
            static fn(array $column): string => (string) $column['name'],
            array_values((new SchemaInspector($this->adapter, TestDatabase::metadata($this->adapter)))->getColumns(
                $table,
            )),
        );
        sort($names);

        return $names;
    }
}
