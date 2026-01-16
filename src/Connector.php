<?php

namespace Tivins\Webapp;

use PDO;

abstract class Connector
{
    protected ?PDO $instance;
    protected DatabaseType $databaseType;

    /**
     * Établit la connexion à la base de données.
     *
     * @return static Instance courante pour le chaînage
     */
    abstract public function connect(): static;

    /**
     * Récupère le helper SQL adapté au type de base de données.
     *
     * @return SQLHelper Le helper SQL (MySQLHelper, SQLiteHelper, etc.)
     */
    abstract public function getHelper(): SQLHelper;

    /**
     * Récupère l'instance PDO de la connexion.
     *
     * @return PDO L'instance PDO
     *
     * @example
     * ```php
     * $connector = new MySQLConnector('mydb', 'user', 'pass');
     * $connector->connect();
     * $pdo = $connector->getInstance();
     * ```
     */
    public function getInstance(): PDO {
        return $this->instance;
    }

    /**
     * Ferme la connexion à la base de données.
     *
     * @return void
     */
    abstract public function close(): void;
}

