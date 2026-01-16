<?php

namespace Tivins\Webapp;

use PDO;

class NativeConnector extends Connector
{
    /**
     * Crée un connecteur utilisant une instance PDO existante.
     *
     * Utile lorsque vous avez déjà une connexion PDO et souhaitez
     * l'utiliser avec la classe Database.
     *
     * @param PDO $pdo L'instance PDO existante
     * @param DatabaseType $databaseType Le type de base de données
     *
     * @example
     * ```php
     * $pdo = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'pass');
     * $connector = new NativeConnector($pdo, DatabaseType::MySql);
     * $database = new Database($connector);
     * ```
     */
    public function __construct(PDO $pdo, DatabaseType $databaseType)
    {
        $this->instance = $pdo;
        $this->databaseType = $databaseType;
    }

    /**
     * Retourne l'instance courante (la connexion est déjà établie).
     *
     * @return static Instance courante
     */
    public function connect(): static
    {
        return $this;
    }

    /**
     * Récupère le helper SQL adapté au type de base de données.
     *
     * @return SQLHelper Le helper SQL correspondant (MySQLHelper ou SQLiteHelper)
     */
    public function getHelper(): SQLHelper
    {
        return match ($this->databaseType) {
            DatabaseType::SQLite => new SQLiteHelper(),
            DatabaseType::MySql => new MySqlHelper(),
        };
    }

    /**
     * Ne fait rien (la connexion PDO est gérée en externe).
     *
     * @return void
     */
    public function close(): void
    {
    }
}