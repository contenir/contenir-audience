<?php

declare(strict_types=1);

namespace Contenir\Audience\Schema;

use Override;

/**
 * The consent ledger table: who agreed to each binding's list, to what wording, when and from
 * where.
 *
 * @api
 */
abstract class AbstractCreateConsent extends AbstractAudienceMigration
{
    #[Override]
    protected function mysqlColumns(): string
    {
        $table = $this->table();

        return (
            '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
                . ' `binding` VARCHAR(64) NOT NULL,'
                . ' `email_hash` CHAR(64) NOT NULL,'
                . ' `wording` TEXT NOT NULL,'
                . ' `source` VARCHAR(16) NOT NULL,'
                . ' `ip` VARCHAR(45) NULL,'
                . ' `agreed_at` DATETIME NOT NULL,'
                . ' PRIMARY KEY (`id`),'
                . " KEY `{$table}_person` (`binding`, `email_hash`)"
        );
    }

    #[Override]
    protected function sqliteColumns(): string
    {
        return (
            '"id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,'
                . ' "binding" VARCHAR(64) NOT NULL,'
                . ' "email_hash" CHAR(64) NOT NULL,'
                . ' "wording" TEXT NOT NULL,'
                . ' "source" VARCHAR(16) NOT NULL,'
                . ' "ip" VARCHAR(45) NULL,'
                . ' "agreed_at" DATETIME NOT NULL'
        );
    }

    #[Override]
    protected function sqliteIndexes(): array
    {
        return ['person' => ['binding', 'email_hash']];
    }

    #[Override]
    protected function table(): string
    {
        return $this->tables()->consent();
    }
}
