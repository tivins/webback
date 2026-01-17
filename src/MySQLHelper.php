<?php

namespace Tivins\Webapp;

/**
 * Helper SQL pour MySQL/MariaDB.
 *
 * Implémente SQLHelper avec la syntaxe spécifique à MySQL.
 * Utilise INT AUTO_INCREMENT pour les clés primaires auto-incrémentées
 * et TINYINT(1) pour les booléens.
 */
class MySQLHelper implements SQLHelper
{
    const string CurrentTimestamp = 'CURRENT_TIMESTAMP';

    public function getAutoincrement(string $name): string
    {
        return "$name INT AUTO_INCREMENT PRIMARY KEY";
    }

    public function getText(string $name, ?int $length = null, bool $unique = false, bool $notNull = false): string
    {
        $type = $length !== null ? "VARCHAR($length)" : "TEXT";
        return "$name $type" . ($notNull ? " NOT NULL" : "") . ($unique ? " UNIQUE" : "");
    }

    public function getInteger(string $name, bool $unique = false, bool $notNull = false, string $default = ''): string
    {
        return "$name INT" . ($notNull ? " NOT NULL" : "") . ($unique ? " UNIQUE" : "") . ($default ? " DEFAULT $default" : "");
    }

    public function getReal(string $name, bool $unique = false, bool $notNull = false, string $default = ''): string
    {
        return "$name DOUBLE" . ($notNull ? " NOT NULL" : "") . ($unique ? " UNIQUE" : "") . ($default ? " DEFAULT $default" : "");
    }

    public function getBoolean(string $name, bool $notNull = false, bool $default = false): string
    {
        $defaultValue = $default ? '1' : '0';
        return "$name TINYINT(1)" . ($notNull ? " NOT NULL" : "") . " DEFAULT $defaultValue";
    }

    public function getBlob(string $name, bool $notNull = false): string
    {
        return "$name BLOB" . ($notNull ? " NOT NULL" : "");
    }

    public function getDecimal(string $name, int $precision = 10, int $scale = 2, bool $notNull = false, string $default = ''): string
    {
        return "$name DECIMAL($precision,$scale)" . ($notNull ? " NOT NULL" : "") . ($default ? " DEFAULT $default" : "");
    }

    public function getDateTime(string $name, bool $notNull = false, string $default = ''): string
    {
        return "$name DATETIME" . ($notNull ? " NOT NULL" : "") . ($default ? " DEFAULT $default" : "");
    }

    public function getUniqueKey(array $columns, ?string $name = null): string
    {
        $cols = implode(',', $columns);
        if ($name !== null) {
            return "CONSTRAINT $name UNIQUE ($cols)";
        }
        return "UNIQUE ($cols)";
    }

    public function getIndex(array $columns, ?string $name = null): string
    {
        $cols = implode(',', $columns);
        if ($name !== null) {
            return "INDEX $name ($cols)";
        }
        // Génère un nom d'index par défaut si non fourni
        $indexName = 'idx_' . implode('_', $columns);
        return "INDEX $indexName ($cols)";
    }

    public function createIndex(string $tableName, array $columns, ?string $name = null): string
    {
        $cols = implode(',', $columns);
        $indexName = $name ?? 'idx_' . implode('_', $columns);
        return "CREATE INDEX IF NOT EXISTS $indexName ON $tableName ($cols)";
    }

    public function createTable(string $tableName, string ...$decl): string
    {
        return "CREATE TABLE IF NOT EXISTS $tableName (" . implode(',', $decl) . ")";
    }
}
