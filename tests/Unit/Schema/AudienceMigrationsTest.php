<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit\Schema;

use Contenir\Audience\Schema\AbstractAudienceMigration;
use Contenir\Audience\Tests\TestAsset\Schema\Version20261105000100CreateOutbox;
use Contenir\Audience\Tests\TestAsset\Schema\Version20261105000200CreateSubject;
use Contenir\Audience\Tests\TestAsset\Schema\Version20261105000300CreateSuppression;
use Contenir\Audience\Tests\TestAsset\Schema\Version20261105000400CreateConsent;
use Contenir\Audience\Tests\Trait\AdapterPlatformStubTrait;
use PhpDb\Migration\SchemaInspector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The SQL each audience table's migration runs, as an add-on's subclass gives its prefix: here
 * the test add-on's "test_audience_".
 */
#[Group('unit')]
final class AudienceMigrationsTest extends TestCase
{
    use AdapterPlatformStubTrait;

    private const string MYSQL_TABLE_OPTIONS = ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    /**
     * @return array<string, array{class-string<AbstractAudienceMigration>, string, list<string>, list<string>}>
     */
    public static function migrationProvider(): array
    {
        return [
            'the outbox'         => [
                Version20261105000100CreateOutbox::class,
                'test_audience_outbox',
                [
                    'CREATE TABLE `test_audience_outbox` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
                        . ' `binding` VARCHAR(64) NOT NULL,'
                        . ' `action` VARCHAR(16) NOT NULL,'
                        . ' `email` VARCHAR(320) NULL,'
                        . ' `email_hash` CHAR(64) NOT NULL,'
                        . ' `payload` MEDIUMTEXT NULL,'
                        . ' `consent` TEXT NULL,'
                        . " `state` VARCHAR(16) NOT NULL DEFAULT 'waiting',"
                        . ' `attempts` INT UNSIGNED NOT NULL DEFAULT 0,'
                        . ' `next_attempt_at` DATETIME NOT NULL,'
                        . ' `last_error` TEXT NULL,'
                        . ' `created_at` DATETIME NOT NULL,'
                        . ' `updated_at` DATETIME NOT NULL,'
                        . ' PRIMARY KEY (`id`),'
                        . ' KEY `test_audience_outbox_due` (`state`, `next_attempt_at`),'
                        . ' KEY `test_audience_outbox_person` (`binding`, `email_hash`)'
                        . self::MYSQL_TABLE_OPTIONS,
                ],
                [
                    'CREATE TABLE "test_audience_outbox" ("id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,'
                        . ' "binding" VARCHAR(64) NOT NULL,'
                        . ' "action" VARCHAR(16) NOT NULL,'
                        . ' "email" VARCHAR(320) NULL,'
                        . ' "email_hash" CHAR(64) NOT NULL,'
                        . ' "payload" TEXT NULL,'
                        . ' "consent" TEXT NULL,'
                        . " \"state\" VARCHAR(16) NOT NULL DEFAULT 'waiting',"
                        . ' "attempts" INTEGER NOT NULL DEFAULT 0,'
                        . ' "next_attempt_at" DATETIME NOT NULL,'
                        . ' "last_error" TEXT NULL,'
                        . ' "created_at" DATETIME NOT NULL,'
                        . ' "updated_at" DATETIME NOT NULL)',
                    'CREATE INDEX IF NOT EXISTS "test_audience_outbox_due" ON "test_audience_outbox" ("state", "next_attempt_at")',
                    'CREATE INDEX IF NOT EXISTS "test_audience_outbox_person" ON "test_audience_outbox" ("binding", "email_hash")',
                ],
            ],
            'the sync state'     => [
                Version20261105000200CreateSubject::class,
                'test_audience_subject',
                [
                    'CREATE TABLE `test_audience_subject` (`binding` VARCHAR(64) NOT NULL,'
                        . ' `email_hash` CHAR(64) NOT NULL,'
                        . ' `remote_id` VARCHAR(128) NULL,'
                        . ' `values_hash` CHAR(64) NOT NULL,'
                        . ' `subscribed` TINYINT(1) NOT NULL DEFAULT 0,'
                        . ' `synced_at` DATETIME NOT NULL,'
                        . ' PRIMARY KEY (`binding`, `email_hash`)'
                        . self::MYSQL_TABLE_OPTIONS,
                ],
                [
                    'CREATE TABLE "test_audience_subject" ("binding" VARCHAR(64) NOT NULL,'
                        . ' "email_hash" CHAR(64) NOT NULL,'
                        . ' "remote_id" VARCHAR(128) NULL,'
                        . ' "values_hash" CHAR(64) NOT NULL,'
                        . ' "subscribed" INTEGER NOT NULL DEFAULT 0,'
                        . ' "synced_at" DATETIME NOT NULL,'
                        . ' PRIMARY KEY ("binding", "email_hash"))',
                ],
            ],
            'the suppression'    => [
                Version20261105000300CreateSuppression::class,
                'test_audience_suppression',
                [
                    'CREATE TABLE `test_audience_suppression` (`binding` VARCHAR(64) NOT NULL,'
                        . ' `email_hash` CHAR(64) NOT NULL,'
                        . ' `reason` VARCHAR(16) NOT NULL,'
                        . ' `suppressed_at` DATETIME NOT NULL,'
                        . ' PRIMARY KEY (`binding`, `email_hash`)'
                        . self::MYSQL_TABLE_OPTIONS,
                ],
                [
                    'CREATE TABLE "test_audience_suppression" ("binding" VARCHAR(64) NOT NULL,'
                        . ' "email_hash" CHAR(64) NOT NULL,'
                        . ' "reason" VARCHAR(16) NOT NULL,'
                        . ' "suppressed_at" DATETIME NOT NULL,'
                        . ' PRIMARY KEY ("binding", "email_hash"))',
                ],
            ],
            'the consent ledger' => [
                Version20261105000400CreateConsent::class,
                'test_audience_consent',
                [
                    'CREATE TABLE `test_audience_consent` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
                        . ' `binding` VARCHAR(64) NOT NULL,'
                        . ' `email_hash` CHAR(64) NOT NULL,'
                        . ' `wording` TEXT NOT NULL,'
                        . ' `source` VARCHAR(16) NOT NULL,'
                        . ' `ip` VARCHAR(45) NULL,'
                        . ' `agreed_at` DATETIME NOT NULL,'
                        . ' PRIMARY KEY (`id`),'
                        . ' KEY `test_audience_consent_person` (`binding`, `email_hash`)'
                        . self::MYSQL_TABLE_OPTIONS,
                ],
                [
                    'CREATE TABLE "test_audience_consent" ("id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,'
                        . ' "binding" VARCHAR(64) NOT NULL,'
                        . ' "email_hash" CHAR(64) NOT NULL,'
                        . ' "wording" TEXT NOT NULL,'
                        . ' "source" VARCHAR(16) NOT NULL,'
                        . ' "ip" VARCHAR(45) NULL,'
                        . ' "agreed_at" DATETIME NOT NULL)',
                    'CREATE INDEX IF NOT EXISTS "test_audience_consent_person" ON "test_audience_consent" ("binding", "email_hash")',
                ],
            ],
        ];
    }

    /**
     * @param class-string<AbstractAudienceMigration> $class
     * @param list<string>                            $mysql
     */
    #[DataProvider('migrationProvider')]
    #[Test]
    public function createsTheTableUnderTheAddOnsPrefixOnMysql(string $class, string $table, array $mysql): void
    {
        static::assertSame($mysql, $this->preview($class, $table, 'MySQL', exists: false));
    }

    /**
     * @param class-string<AbstractAudienceMigration> $class
     * @param list<string>                            $mysql
     * @param list<string>                            $sqlite
     */
    #[DataProvider('migrationProvider')]
    #[Test]
    public function createsTheTableUnderTheAddOnsPrefixOnSqlite(
        string $class,
        string $table,
        array $mysql,
        array $sqlite,
    ): void {
        static::assertSame($sqlite, $this->preview($class, $table, 'SQLite', exists: false));
    }

    /**
     * @param class-string<AbstractAudienceMigration> $class
     */
    #[DataProvider('migrationProvider')]
    #[Test]
    public function describesItselfByItsTable(string $class, string $table): void
    {
        static::assertSame("Create the {$table} table", (new $class())->getDescription());
    }

    /**
     * @param class-string<AbstractAudienceMigration> $class
     */
    #[DataProvider('migrationProvider')]
    #[Test]
    public function leavesAnExistingMysqlTable(string $class, string $table): void
    {
        static::assertSame([], $this->preview($class, $table, 'MySQL', exists: true));
    }

    #[Test]
    public function onlyEnsuresTheIndexesOfAnExistingSqliteTable(): void
    {
        static::assertSame(
            [
                'CREATE INDEX IF NOT EXISTS "test_audience_outbox_due" ON "test_audience_outbox" ("state", "next_attempt_at")',
                'CREATE INDEX IF NOT EXISTS "test_audience_outbox_person" ON "test_audience_outbox" ("binding", "email_hash")',
            ],
            $this->preview(Version20261105000100CreateOutbox::class, 'test_audience_outbox', 'SQLite', exists: true),
        );
    }

    /**
     * @param class-string<AbstractAudienceMigration> $class
     *
     * @return array<string>
     */
    private function preview(string $class, string $table, string $platform, bool $exists): array
    {
        $inspector = $this->createStub(SchemaInspector::class);
        $inspector->method('tableExists')->willReturnMap([[$table, $exists]]);

        return (new $class())->preview($this->adapterNamed($platform), $inspector);
    }
}
