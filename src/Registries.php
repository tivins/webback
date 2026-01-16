<?php

declare(strict_types=1);

namespace Tivins\Webapp;

/**
 * Gestionnaire centralisé des instances de registries (pattern singleton + lazy loading)
 *
 * Cette classe permet de centraliser l'accès aux registries de l'application en garantissant
 * qu'une seule instance de chaque registry existe (pattern singleton) et qu'elle n'est créée
 * qu'au moment de son premier accès (lazy loading).
 *
 * Pourquoi l'utiliser ?
 * - Singleton : Évite la création de multiples instances d'une même registry, économisant
 *   la mémoire et garantissant la cohérence des données
 * - Lazy loading : Les registries ne sont instanciées que lorsqu'elles sont réellement
 *   utilisées, améliorant les performances au démarrage
 * - Centralisation : Point d'accès unique pour toutes les registries de l'application,
 *   facilitant la maintenance et les tests
 * - Typage fort : En étendant cette classe et créant des méthodes typées (voir exemple),
 *   on obtient un accès typé et autocomplété aux registries
 *
 * Exemple d'utilisation :
 *
 * <code>
 * // Initialisation (une seule fois au démarrage de l'application)
 * Registries::init($database);
 *
 * class AppRegistries extends Registries {
 *     public static function users(): UserRegistry {
 *         return self::get(UserRegistry::class);
 *     }
 * }
 *
 * // Instance créée au premier appel (utilise la database par défaut) :
 * $userRegistry = AppRegistries::users();
 * // Retourne la même instance :
 * $userRegistry2 = AppRegistries::users();
 *
 * // Il est toujours possible de passer une database explicite si nécessaire :
 * $otherRegistry = AppRegistries::get(UserRegistry::class, $otherDatabase);
 * </code>
 */
class Registries
{
    private static array $instances = [];
    private static ?Database $database = null;
    /** @var array<string, Database> Association entre une classe de registry et sa Database spécifique */
    private static array $classDatabases = [];

    /**
     * Initialise la Database par défaut à utiliser pour toutes les registries.
     *
     * Cette méthode doit être appelée une fois au démarrage de l'application.
     * Après l'initialisation, il n'est plus nécessaire de passer la Database
     * à chaque appel de get().
     *
     * @param Database $database La Database par défaut à utiliser
     * @return void
     *
     * @example
     * ```php
     * $database = new Database(new MySQLConnector(...));
     * Registries::init($database);
     * ```
     */
    public static function init(Database $database): void
    {
        self::$database = $database;
    }

    /**
     * Récupère une instance de registry (lazy loading + singleton)
     *
     * Comportement selon le paramètre $database :
     * - Si $database n'est pas fourni :
     *   - S'il existe une Database associée à $class (via un appel précédent avec $database),
     *     cette Database est utilisée
     *   - Sinon, utilise la Database par défaut (définie via init())
     *   - Si aucune Database n'est disponible, lève une exception
     * - Si $database est fourni :
     *   - L'association entre $class et cette Database est stockée
     *   - Cette Database est utilisée pour créer/récupérer l'instance de registry
     *
     * @template R of DatabaseRegistry
     * @param class-string<R> $class Le nom complet de la classe registry
     * @param Database|null $database La Database à utiliser et associer à $class (optionnel)
     * @return R
     * @throws \RuntimeException Si aucune Database n'est disponible
     *
     * @example
     *
     * <code>
     * // Utilise la database par défaut
     * $registry = Registries::get(UserRegistry::class);
     *
     * // Associe une database spécifique à UserRegistry et l'utilise
     * $registry = Registries::get(UserRegistry::class, $otherDatabase);
     * // Les appels suivants sans $database utiliseront automatiquement $otherDatabase
     * $registry2 = Registries::get(UserRegistry::class); // Utilise $otherDatabase
     * </code>
     */
    public static function get(string $class, ?Database $database = null): DatabaseRegistry
    {
        if ($database !== null) {
            // Si une Database est fournie, on l'associe à la classe
            self::$classDatabases[$class] = $database;
            $db = $database;
        } else {
            // Si aucune Database n'est fournie, on cherche d'abord une association
            // puis on utilise la Database par défaut
            $db = self::$classDatabases[$class] ?? self::$database;
            if ($db === null) {
                throw new \RuntimeException(
                    "Registries not initialized. Call Registries::init(\$database) first or provide a Database to get()."
                );
            }
        }

        // Utilise l'ID de l'objet Database pour différencier les instances
        // Cela permet d'avoir des registries différentes pour différentes databases
        $key = $class . '|' . spl_object_id($db);
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new $class($db);
        }
        return self::$instances[$key];
    }

    /**
     * Libère une instance de registry du cache.
     *
     * Utile pour forcer la recréation d'une registry lors des tests
     * ou pour libérer la mémoire.
     *
     * Note: Cette méthode libère toutes les instances de la classe pour toutes les Databases.
     * L'association Database-classe est conservée.
     *
     * @template R of DatabaseRegistry
     * @param class-string<R> $class Le nom complet de la classe registry à libérer
     * @return void
     *
     * @example
     * ```php
     * // Libère toutes les instances de UserRegistry
     * Registries::release(UserRegistry::class);
     * // Le prochain appel à get() créera une nouvelle instance
     * ```
     */
    public static function release(string $class): void
    {
        // Libère toutes les instances de cette classe (pour toutes les Databases)
        foreach (self::$instances as $key => $instance) {
            if (str_starts_with($key, $class . '|')) {
                unset(self::$instances[$key]);
            }
        }
    }
}