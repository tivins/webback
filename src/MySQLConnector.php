<?php

namespace Tivins\Webapp;

use PDO;
use SensitiveParameter;

class MySQLConnector extends Connector
{
    /**
     * Crée un connecteur MySQL/MariaDB.
     *
     * @param string $dbName Le nom de la base de données
     * @param string $user Le nom d'utilisateur
     * @param string $pass Le mot de passe
     * @param string $host L'adresse du serveur (par défaut: 'localhost')
     * @param int $port Le port du serveur (par défaut: 3306)
     *
     * @example
     * ```php
     * $connector = new MySQLConnector('mydb', 'user', 'password', 'localhost', 3306);
     * $connector->connect();
     * ```
     */
    public function __construct(
        private readonly string                       $dbName,
        private readonly string                       $user,
        #[SensitiveParameter] private readonly string $pass,
        private readonly string                       $host = 'localhost',
        private readonly int                          $port = 3306,
    )
    {
    }

    /**
     * Établit la connexion à la base de données MySQL.
     *
     * @return static Instance courante pour le chaînage
     *
     * @example
     * ```php
     * $connector = new MySQLConnector('mydb', 'user', 'pass');
     * $connector->connect();
     * ```
     */
    public function connect(): static
    {
        $this->instance = new PDO("mysql:host=$this->host;dbname=$this->dbName;port=$this->port", $this->user, $this->pass);
        return $this;
    }

    /**
     * Récupère le helper SQL pour MySQL.
     *
     * @return SQLHelper Une instance de MySQLHelper
     */
    public function getHelper(): SQLHelper
    {
        return new MySQLHelper();
    }

    /**
     * Ferme la connexion à la base de données.
     *
     * @return void
     */
    public function close(): void
    {
        $this->instance = null;
    }
}