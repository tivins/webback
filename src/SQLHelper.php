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
     * Génère une déclaration de contrainte UNIQUE sur plusieurs colonnes.
     *
     * @param array $columns Les noms des colonnes (ex: ['title', 'author'])
     * @param string|null $name Le nom optionnel de la contrainte
     * @return string La déclaration SQL (ex: "UNIQUE (title, author)" ou "CONSTRAINT name UNIQUE (title, author)")
     *
     * @example
     * ```php
     * $helper->getUniqueKey(['title', 'author']);
     * // Retourne: "UNIQUE (title, author)"
     *
     * $helper->getUniqueKey(['title', 'author'], 'unique_title_author');
     * // Retourne: "CONSTRAINT unique_title_author UNIQUE (title, author)" (MySQL)
     * // ou "UNIQUE (title, author)" (SQLite)
     * ```
     */
    public function getUniqueKey(array $columns, ?string $name = null): string;

    /**
     * Génère une déclaration d'index sur plusieurs colonnes.
     *
     * Pour MySQL, cette méthode retourne une déclaration qui peut être utilisée dans CREATE TABLE.
     * Pour SQLite, cette méthode retourne une instruction CREATE INDEX complète (car SQLite ne supporte pas INDEX dans CREATE TABLE).
     *
     * @param array $columns Les noms des colonnes (ex: ['title', 'author'])
     * @param string|null $name Le nom optionnel de l'index
     * @return string La déclaration SQL (ex: "INDEX idx_name (title, author)" pour MySQL dans CREATE TABLE, ou "CREATE INDEX idx_name ON table (title, author)" pour SQLite)
     *
     * @example
     * ```php
     * // MySQL - peut être utilisé dans createTable
     * $helper->getIndex(['title', 'author']);
     * // Retourne: "INDEX idx_title_author (title, author)"
     *
     * // SQLite - doit être exécuté séparément après createTable
     * $helper->getIndex(['title', 'author'], 'idx_title_author');
     * // Retourne: "CREATE INDEX IF NOT EXISTS idx_title_author ON table_name (title, author)"
     * ```
     */
    public function getIndex(array $columns, ?string $name = null): string;

    /**
     * Génère une instruction CREATE INDEX complète pour créer un index séparément.
     *
     * Cette méthode est utile pour SQLite (qui ne supporte pas INDEX dans CREATE TABLE)
     * mais peut aussi être utilisée pour MySQL si on préfère créer l'index après la table.
     *
     * @param string $tableName Le nom de la table
     * @param array $columns Les noms des colonnes (ex: ['title', 'author'])
     * @param string|null $name Le nom optionnel de l'index
     * @return string L'instruction SQL CREATE INDEX
     *
     * @example
     * ```php
     * $helper->createIndex('books', ['title', 'author'], 'idx_title_author');
     * // Retourne: "CREATE INDEX IF NOT EXISTS idx_title_author ON books (title, author)"
     * ```
     */
    public function createIndex(string $tableName, array $columns, ?string $name = null): string;

    /**
     * Génère une instruction CREATE TABLE.
     *
     * @param string $tableName Le nom de la table
     * @param string ...$decl Les déclarations de colonnes et contraintes
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
     *
     * $sql = $helper->createTable('books',
     *     $helper->getAutoincrement('id'),
     *     $helper->getText('title', 255, false, true),
     *     $helper->getText('author', 255, false, true),
     *     $helper->getUniqueKey(['title', 'author'])
     * );
     * // Retourne: "CREATE TABLE IF NOT EXISTS books (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, author VARCHAR(255) NOT NULL, UNIQUE (title, author))"
     * ```
     */
    public function createTable(string $tableName, string ...$decl): string;
}