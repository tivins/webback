<?php

namespace Tivins\Webapp;

use PDO;
use PDOStatement;

readonly class SQLStatement
{
    /**
     * Crée un wrapper autour d'un PDOStatement.
     *
     * @param PDOStatement $stmt Le statement PDO à encapsuler
     */
    public function __construct(private PDOStatement $stmt)
    {
    }

    /**
     * Récupère toutes les lignes du résultat.
     *
     * @return array<object> Tableau d'objets représentant chaque ligne
     *
     * @example
     * ```php
     * $stmt = $database->execute('SELECT * FROM users');
     * $users = $stmt->fetchAll();
     * // Retourne: [object{id:1, name:'John'}, object{id:2, name:'Jane'}]
     * ```
     */
    public function fetchAll(): array
    {
        return $this->stmt->fetchAll();
    }

    /**
     * Récupère la prochaine ligne du résultat.
     *
     * @return object|null Un objet représentant la ligne ou null s'il n'y a plus de lignes
     *
     * @example
     * ```php
     * $stmt = $database->execute('SELECT * FROM users WHERE id = ?', [123]);
     * $user = $stmt->fetch();
     * if ($user) {
     *     echo $user->name;
     * }
     * ```
     */
    public function fetch(): ?object
    {
        return $this->stmt->fetch()
            ?: null;
    }

    /**
     * Récupère la valeur de la première colonne de la prochaine ligne.
     *
     * @return mixed La valeur de la colonne ou false s'il n'y a plus de lignes
     *
     * @example
     * ```php
     * $stmt = $database->execute('SELECT COUNT(*) FROM users');
     * $count = $stmt->fetchField();
     * // Retourne le nombre d'utilisateurs
     * ```
     */
    public function fetchField(): mixed
    {
        return $this->stmt->fetchColumn();
    }

    /**
     * Récupère toutes les valeurs d'une colonne spécifique.
     *
     * @param int $idx L'index de la colonne (0 pour la première colonne)
     * @return array Tableau des valeurs de la colonne
     *
     * @example
     * ```php
     * $stmt = $database->execute('SELECT id, name, email FROM users');
     * $names = $stmt->fetchCol(1); // Récupère toutes les valeurs de la colonne 'name'
     * // Retourne: ['John', 'Jane', 'Bob']
     * ```
     */
    public function fetchCol(int $idx = 0): array
    {
        return $this->stmt->fetchAll(PDO::FETCH_COLUMN, $idx);
    }

    /**
     * Retourne le nombre de lignes affectées par la dernière requête.
     *
     * @return int Le nombre de lignes affectées
     *
     * @example
     * ```php
     * $stmt = $database->execute('UPDATE users SET status = ? WHERE active = 1', ['inactive']);
     * $rows = $stmt->rowCount();
     * // Retourne le nombre d'utilisateurs mis à jour
     * ```
     */
    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }
}
