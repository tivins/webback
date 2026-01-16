<?php

namespace Tivins\Webapp;

/**
 * Interface pour générer des déclarations SQL spécifiques à chaque type de base de données.
 *
 * Permet de créer des déclarations de colonnes et de tables compatibles
 * avec différents SGBD (MySQL, SQLite, etc.).
 */
interface SQLHelper
{
    /**
     * Génère une déclaration de colonne auto-incrémentée (clé primaire).
     *
     * @param string $name Le nom de la colonne
     * @return string La déclaration SQL (ex: "id INT AUTO_INCREMENT PRIMARY KEY" pour MySQL)
     *
     * @example
     * ```php
     * $helper->getAutoincrement('id');
     * // MySQL: "id INT AUTO_INCREMENT PRIMARY KEY"
     * // SQLite: "id INTEGER PRIMARY KEY AUTOINCREMENT"
     * ```
     */
    public function getAutoincrement(string $name): string;

    /**
     * Génère une déclaration de colonne texte.
     *
     * @param string $name Le nom de la colonne
     * @param int|null $length La longueur maximale (null pour TEXT illimité)
     * @param bool $unique Si la colonne doit être unique
     * @param bool $notNull Si la colonne ne peut pas être NULL
     * @return string La déclaration SQL
     *
     * @example
     * ```php
     * $helper->getText('name', 255, false, true);
     * // Retourne: "name VARCHAR(255) NOT NULL"
     * ```
     */
    public function getText(string $name, ?int $length = null, bool $unique = false, bool $notNull = false): string;

    /**
     * Génère une déclaration de colonne entière.
     *
     * @param string $name Le nom de la colonne
     * @param bool $unique Si la colonne doit être unique
     * @param bool $notNull Si la colonne ne peut pas être NULL
     * @param string $default La valeur par défaut
     * @return string La déclaration SQL
     *
     * @example
     * ```php
     * $helper->getInteger('age', false, true, '0');
     * // Retourne: "age INT NOT NULL DEFAULT 0"
     * ```
     */
    public function getInteger(string $name, bool $unique = false, bool $notNull = false, string $default = ''): string;

    /**
     * Génère une déclaration de colonne nombre réel (flottant).
     *
     * @param string $name Le nom de la colonne
     * @param bool $unique Si la colonne doit être unique
     * @param bool $notNull Si la colonne ne peut pas être NULL
     * @param string $default La valeur par défaut
     * @return string La déclaration SQL
     *
     * @example
     * ```php
     * $helper->getReal('price', false, true, '0.0');
     * ```
     */
    public function getReal(string $name, bool $unique = false, bool $notNull = false, string $default = ''): string;

    /**
     * Génère une déclaration de colonne booléenne.
     *
     * @param string $name Le nom de la colonne
     * @param bool $notNull Si la colonne ne peut pas être NULL
     * @param bool $default La valeur par défaut
     * @return string La déclaration SQL
     *
     * @example
     * ```php
     * $helper->getBoolean('active', true, true);
     * // Retourne: "active TINYINT(1) NOT NULL DEFAULT 1" (MySQL)
     * ```
     */
    public function getBoolean(string $name, bool $notNull = false, bool $default = false): string;

    /**
     * Génère une déclaration de colonne BLOB (données binaires).
     *
     * @param string $name Le nom de la colonne
     * @param bool $notNull Si la colonne ne peut pas être NULL
     * @return string La déclaration SQL
     *
     * @example
     * ```php
     * $helper->getBlob('avatar', true);
     * ```
     */
    public function getBlob(string $name, bool $notNull = false): string;

    /**
     * Génère une déclaration de colonne décimale.
     *
     * @param string $name Le nom de la colonne
     * @param int $precision Le nombre total de chiffres
     * @param int $scale Le nombre de chiffres après la virgule
     * @param bool $notNull Si la colonne ne peut pas être NULL
     * @param string $default La valeur par défaut
     * @return string La déclaration SQL
     *
     * @example
     * ```php
     * $helper->getDecimal('amount', 10, 2, true, '0.00');
     * // Retourne: "amount DECIMAL(10,2) NOT NULL DEFAULT 0.00" (MySQL)
     * ```
     */
    public function getDecimal(string $name, int $precision = 10, int $scale = 2, bool $notNull = false, string $default = ''): string;

    /**
     * Génère une déclaration de colonne date/heure.
     *
     * @param string $name Le nom de la colonne
     * @param bool $notNull Si la colonne ne peut pas être NULL
     * @param string $default La valeur par défaut (ex: 'CURRENT_TIMESTAMP')
     * @return string La déclaration SQL
     *
     * @example
     * ```php
     * $helper->getDateTime('created_at', true, 'CURRENT_TIMESTAMP');
     * ```
     */
    public function getDateTime(string $name, bool $notNull = false, string $default = ''): string;

    /**
     * Génère une instruction CREATE TABLE.
     *
     * @param string $tableName Le nom de la table
     * @param string ...$decl Les déclarations de colonnes
     * @return string L'instruction SQL CREATE TABLE
     *
     * @example
     * ```php
     * $sql = $helper->createTable('users',
     *     $helper->getAutoincrement('id'),
     *     $helper->getText('name', 255, false, true),
     *     $helper->getText('email', 255, true, true)
     * );
     * // Retourne: "CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255) UNIQUE NOT NULL)"
     * ```
     */
    public function createTable(string $tableName, string ...$decl): string;
}