<?php

declare(strict_types=1);

namespace Contenir\Audience\Schema;

use Contenir\Audience\Storage\Tables;
use Override;
use PhpDb\Migration\AbstractMigration;
use UnexpectedValueException;

use function implode;

/**
 * One of the audience tables, created under an add-on's prefix. Each add-on ships a subclass of
 * each table's migration that gives its prefix and a version of its own:
 *
 *     final class Version20261105000100CreateOutbox extends AbstractCreateOutbox
 *     {
 *         public function getVersion(): string { return '20261105000100'; }
 *         protected function prefix(): string { return 'ext_mailchimp_'; }
 *     }
 *
 * @api
 */
abstract class AbstractAudienceMigration extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return "Create the {$this->table()} table";
    }

    /**
     * The MySQL definition of the table's columns and keys, between the parentheses.
     */
    abstract protected function mysqlColumns(): string;

    /**
     * The add-on's table prefix, such as "ext_mailchimp_".
     */
    abstract protected function prefix(): string;

    /**
     * The SQLite definition of the table's columns, between the parentheses.
     */
    abstract protected function sqliteColumns(): string;

    /**
     * The indexes SQLite creates apart from the table, by name: each a list of columns.
     *
     * @return array<string, list<string>>
     */
    abstract protected function sqliteIndexes(): array;

    /**
     * The table's name under the add-on's prefix.
     */
    abstract protected function table(): string;

    /**
     * @throws UnexpectedValueException On a database other than MySQL or SQLite.
     */
    #[Override]
    protected function define(): void
    {
        $table  = $this->table();
        $exists = $this->inspector->tableExists($table);
        if (Platform::isMysql($this->adapter)) {
            $this->executeSqlIf(
                ! $exists,
                "CREATE TABLE `{$table}` ({$this->mysqlColumns()}) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "Create table \"{$table}\"",
                "Table \"{$table}\" already exists",
            );

            return;
        }

        $this->executeSqlIf(
            ! $exists,
            "CREATE TABLE \"{$table}\" ({$this->sqliteColumns()})",
            "Create table \"{$table}\"",
            "Table \"{$table}\" already exists",
        );
        foreach ($this->sqliteIndexes() as $name => $columns) {
            $index = "{$table}_{$name}";
            $this->executeSql(
                "CREATE INDEX IF NOT EXISTS \"{$index}\" ON \"{$table}\" (\"" . implode('", "', $columns) . '")',
                "Index \"{$table}.{$index}\"",
            );
        }
    }

    protected function tables(): Tables
    {
        return new Tables($this->prefix());
    }
}
