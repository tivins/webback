<?php

namespace Tivins\Webapp;

/**
 * Helper SQL pour SQLite.
 *
 * Implémente SQLHelper avec la syntaxe spécifique à SQLite.
 * Utilise INTEGER PRIMARY KEY AUTOINCREMENT pour les clés primaires
 * et INTEGER pour les booléens.
 */
class SQLiteHelper implements SQLHelper
{
    const string CurrentTimestamp = 'CURRENT_TIMESTAMP';

    public function getAutoincrement(string $name): string
    {
        return "$name INTEGER PRIMARY KEY AUTOINCREMENT";
    }

    public function getText(string $name, ?int $length = null, bool $unique = false, bool $notNull = false): string
    {
        $type = $length !== null ? "VARCHAR($length)" : "TEXT";
        return "$name $type" . ($notNull ? " NOT NULL" : "") . ($unique ? " UNIQUE" : "");
    }

    public function getInteger(string $name, bool $unique = false, bool $notNull = false, string $default = ''): string
    {
        return "$name INTEGER" . ($notNull ? " NOT NULL" : "") . ($unique ? " UNIQUE" : "") . ($default ? " DEFAULT $default" : "");
    }

    public function getReal(string $name, bool $unique = false, bool $notNull = false, string $default = ''): string
    {
        return "$name REAL" . ($notNull ? " NOT NULL" : "") . ($unique ? " UNIQUE" : "") . ($default ? " DEFAULT $default" : "");
    }

    public function getBoolean(string $name, bool $notNull = false, bool $default = false): string
    {
        $defaultValue = $default ? '1' : '0';
        return "$name INTEGER" . ($notNull ? " NOT NULL" : "") . " DEFAULT $defaultValue";
    }

    public function getBlob(string $name, bool $notNull = false): string
    {
        return "$name BLOB" . ($notNull ? " NOT NULL" : "");
    }

    public function getDecimal(string $name, int $precision = 10, int $scale = 2, bool $notNull = false, string $default = ''): string
    {
        return "$name NUMERIC($precision,$scale)" . ($notNull ? " NOT NULL" : "") . ($default ? " DEFAULT $default" : "");
    }

    public function getDateTime(string $name, bool $notNull = false, string $default = ''): string
    {
        return "$name DATETIME" . ($notNull ? " NOT NULL" : "") . ($default ? " DEFAULT $default" : "");
    }

    public function createTable(string $tableName, string ...$decl): string
    {
        return "CREATE TABLE IF NOT EXISTS $tableName (" . implode(',', $decl) . ")";
    }
}