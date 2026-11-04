<?php

declare(strict_types=1);

namespace Contenir\Audience\Schema;

use Override;

/**
 * The webhook table: the webhook each binding registered at the service, with its id there and
 * the secret the service signs its calls with.
 *
 * @api
 */
abstract class AbstractCreateWebhook extends AbstractAudienceMigration
{
    #[Override]
    protected function mysqlColumns(): string
    {
        return (
            '`binding` VARCHAR(64) NOT NULL,'
                . ' `remote_id` VARCHAR(128) NOT NULL,'
                . ' `signing_secret` VARCHAR(255) NULL,'
                . ' `registered_at` DATETIME NOT NULL,'
                . ' PRIMARY KEY (`binding`)'
        );
    }

    #[Override]
    protected function sqliteColumns(): string
    {
        return (
            '"binding" VARCHAR(64) NOT NULL,'
                . ' "remote_id" VARCHAR(128) NOT NULL,'
                . ' "signing_secret" VARCHAR(255) NULL,'
                . ' "registered_at" DATETIME NOT NULL,'
                . ' PRIMARY KEY ("binding")'
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
        return $this->tables()->webhook();
    }
}
