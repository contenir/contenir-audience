<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Integration\Storage;

use Contenir\Audience\Consent;
use Contenir\Audience\ConsentSource;
use Contenir\Audience\Storage\PhpDbConsentLedger;
use Contenir\Audience\Tests\TestAsset\Database\TestDatabase;
use Contenir\Audience\Tests\TestAsset\Factory\Make;
use Contenir\Audience\Tests\Trait\TestDatabaseTrait;
use Override;
use PhpDb\Adapter\Adapter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function is_array;

#[Group('integration')]
final class PhpDbConsentLedgerTest extends TestCase
{
    use TestDatabaseTrait;

    #[Test]
    public function recordsWhoAgreedToWhatWhenAndFromWhere(): void
    {
        $ledger = new PhpDbConsentLedger($this->adapter, TestDatabase::tables());

        $ledger->record('newsletter', 'hash-1', Make::consent());
        $ledger->record(
            'newsletter',
            'hash-1',
            new Consent('At the event', ConsentSource::Import, Make::time('2026-11-06')),
        );

        static::assertEquals(
            [
                [
                    'binding'    => 'newsletter',
                    'email_hash' => 'hash-1',
                    'wording'    => 'Signing up to the newsletter',
                    'source'     => 'form',
                    'ip'         => '203.0.113.7',
                    'agreed_at'  => '2026-11-05 09:00:00',
                ],
                [
                    'binding'    => 'newsletter',
                    'email_hash' => 'hash-1',
                    'wording'    => 'At the event',
                    'source'     => 'import',
                    'ip'         => null,
                    'agreed_at'  => '2026-11-06 00:00:00',
                ],
            ],
            $this->stored(),
        );
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
     * @return list<array<string, mixed>>
     */
    private function stored(): array
    {
        $result = $this->adapter->query(
            'SELECT binding, email_hash, wording, source, ip, agreed_at FROM '
                . TestDatabase::tables()->consent()
                . ' ORDER BY id',
            Adapter::QUERY_MODE_EXECUTE,
        );
        $rows = [];
        foreach ($result as $row) {
            $rows[] = is_array($row) ? $row : (array) $row;
        }

        return $rows;
    }
}
