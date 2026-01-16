<?php

namespace Tivins\Webapp;

use PDO;

class SQLiteConnector extends Connector
{
    /**
     * Crée un connecteur SQLite.
     *
     * @param string $file Le chemin vers le fichier de base de données SQLite
     *
     * @example
     * ```php
     * $connector = new SQLiteConnector('/path/to/database.db');
     * $connector->connect();
     * ```
     */
    public function __construct(
        private readonly string $file
    )
    {
    }

    /**
     * Établit la connexion à la base de données SQLite.
     *
     * Crée le fichier s'il n'existe pas.
     *
     * @return static Instance courante pour le chaînage
     *
     * @example
     * ```php
     * $connector = new SQLiteConnector('test.db');
     * $connector->connect();
     * ```
     */
    public function connect(): static
    {
        $this->instance = new PDO("sqlite:$this->file");
        $this->databaseType = DatabaseType::SQLite;
        return $this;
    }

    /**
     * Récupère le helper SQL pour SQLite.
     *
     * @return SQLHelper Une instance de SQLiteHelper
     */
    public function getHelper(): SQLHelper
    {
        return new SQLiteHelper();
    }

    /**
     * Ferme la connexion et supprime le fichier de base de données.
     *
     * ⚠️ Attention: Cette méthode supprime le fichier SQLite.
     * Utilisez avec précaution en production.
     *
     * @return void
     */
    public function close(): void
    {
        $this->instance = null;
        unlink($this->file);
    }
}