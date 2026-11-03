<?php

declare(strict_types=1);

namespace Contenir\Audience\Schema;

use Override;

/**
 * The sync state table: one row per person each binding has sent, by a hash of their email.
 *
 * @api
 */
abstract class AbstractCreateSubject extends AbstractAudienceMigration
{
    #[Override]
    protected function mysqlColumns(): string
    {
        return (
            '`binding` VARCHAR(64) NOT NULL,'
                . ' `email_hash` CHAR(64) NOT NULL,'
                . ' `remote_id` VARCHAR(128) NULL,'
                . ' `values_hash` CHAR(64) NOT NULL,'
                . ' `subscribed` TINYINT(1) NOT NULL DEFAULT 0,'
                . ' `synced_at` DATETIME NOT NULL,'
                . ' PRIMARY KEY (`binding`, `email_hash`)'
        );
    }

    #[Override]
    protected function sqliteColumns(): string
    {
        return (
            '"binding" VARCHAR(64) NOT NULL,'
                . ' "email_hash" CHAR(64) NOT NULL,'
                . ' "remote_id" VARCHAR(128) NULL,'
                . ' "values_hash" CHAR(64) NOT NULL,'
                . ' "subscribed" INTEGER NOT NULL DEFAULT 0,'
                . ' "synced_at" DATETIME NOT NULL,'
                . ' PRIMARY KEY ("binding", "email_hash")'
        );
    }

    #[Override]
    protected function sqliteIndexes(): array
    {
        return [];
    }

    #[Override]
    protected function table(): string
    {
        return $this->tables()->subject();
    }
}
