<?php

declare(strict_types=1);

namespace Tivins\Webapp;

use RuntimeException;

/**
 * Singleton + lazy loading pour les registries.
 * @see Registries.md
 */
class Registries
{
    private static array $instances = [];

    private static ?Database $database = null;

    /** @var array<string, Database> */
    private static array $classDatabases = [];

    public static function init(Database $database): void
    {
        self::$database = $database;
    }

    /**
     * @template R of DatabaseRegistry
     * @param class-string<R> $class
     * @return R
     * @throws RuntimeException Si non initialisée et $database null
     */
    public static function get(string $class, ?Database $database = null): DatabaseRegistry
    {
        if ($database !== null) {
            self::$classDatabases[$class] = $database;
            $db = $database;
        } else {
            $db = self::$classDatabases[$class] ?? self::$database;
            if ($db === null) {
                throw new RuntimeException(
                    "Registries not initialized. Call Registries::init(\$database) first or provide a Database to get()."
                );
            }
        }

        $key = $class . '|' . spl_object_id($db);
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new $class($db);
        }
        return self::$instances[$key];
    }

    /**
     * Libère toutes les instances de $class (toutes Databases confondues).
     * @template R of DatabaseRegistry
     * @param class-string<R> $class
     */
    public static function release(string $class): void
    {
        foreach (self::$instances as $key => $instance) {
            if (str_starts_with($key, $class . '|')) {
                unset(self::$instances[$key]);
            }
        }
    }

    /**
     * Supprime l'association Database pour $class.
     * @template R of DatabaseRegistry
     * @param class-string<R> $class
     */
    public static function unbind(string $class): void
    {
        unset(self::$classDatabases[$class]);
    }

    /**
     * Réinitialise complètement : instances, database par défaut, et associations.
     */
    public static function reset(): void
    {
        self::$instances = [];
        self::$database = null;
        self::$classDatabases = [];
    }
}