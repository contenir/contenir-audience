<?php

declare(strict_types=1);

namespace Contenir\Audience\Schema;

use Override;

/**
 * The suppression table: who each binding must not subscribe, and why.
 *
 * @api
 */
abstract class AbstractCreateSuppression extends AbstractAudienceMigration
{
    #[Override]
    protected function mysqlColumns(): string
    {
        return (
            '`binding` VARCHAR(64) NOT NULL,'
                . ' `email_hash` CHAR(64) NOT NULL,'
                . ' `reason` VARCHAR(16) NOT NULL,'
                . ' `suppressed_at` DATETIME NOT NULL,'
                . ' PRIMARY KEY (`binding`, `email_hash`)'
        );
    }

    #[Override]
    protected function sqliteColumns(): string
    {
        return (
            '"binding" VARCHAR(64) NOT NULL,'
                . ' "email_hash" CHAR(64) NOT NULL,'
                . ' "reason" VARCHAR(16) NOT NULL,'
                . ' "suppressed_at" DATETIME NOT NULL,'
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
        return $this->tables()->suppression();
    }
}
