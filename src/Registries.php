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
 * class AppRegistries extends Registries {
 *     public static function users(Database $database): UserRegistry {
 *         return self::get(UserRegistry::class, $database);
 *     }
 * }
 *
 * // Instance créée au premier appel :
 * $userRegistry = AppRegistries::users($database);
 * // Retourne la même instance :
 * $userRegistry2 = AppRegistries::users($database);
 * </code>
 */
class Registries
{
    private static array $instances = [];

    /**
     * Récupère une instance de registry (lazy loading + singleton)
     *
     * @template R of DatabaseRegistry
     * @param class-string<R> $class
     * @return R
     */
    public static function get(string $class, Database $database): DatabaseRegistry
    {
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new $class($database);
        }
        return self::$instances[$class];
    }

    /**
     * Libère une instance de registry du cache.
     *
     * Utile pour forcer la recréation d'une registry lors des tests
     * ou pour libérer la mémoire.
     *
     * @template R of DatabaseRegistry
     * @param class-string<R> $class Le nom complet de la classe registry à libérer
     * @return void
     *
     * @example
     * ```php
     * // Libère la registry des utilisateurs
     * Registries::release(UserRegistry::class);
     * // Le prochain appel à get() créera une nouvelle instance
     * ```
     */
    public static function release(string $class): void
    {
        unset(self::$instances[$class]);
    }
}