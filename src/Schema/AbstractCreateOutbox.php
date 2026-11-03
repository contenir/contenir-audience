<?php

declare(strict_types=1);

namespace Contenir\Audience\Schema;

use Override;

/**
 * The outbox table: one row per message, with its email and values until it's sent.
 *
 * @api
 */
abstract class AbstractCreateOutbox extends AbstractAudienceMigration
{
    #[Override]
    protected function mysqlColumns(): string
    {
        $table = $this->table();

        return (
            '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
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
                . " KEY `{$table}_due` (`state`, `next_attempt_at`),"
                . " KEY `{$table}_person` (`binding`, `email_hash`)"
        );
    }

    #[Override]
    protected function sqliteColumns(): string
    {
        return (
            '"id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,'
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
                . ' "updated_at" DATETIME NOT NULL'
        );
    }

    #[Override]
    protected function sqliteIndexes(): array
    {
        return ['due' => ['state', 'next_attempt_at'], 'person' => ['binding', 'email_hash']];
    }

    #[Override]
    protected function table(): string
    {
        return $this->tables()->outbox();
    }
}
