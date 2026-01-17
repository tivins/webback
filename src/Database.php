<?php
/** @noinspection PhpUnused */

namespace Tivins\Webapp;

use PDO;

class Database
{
    private ?Logger $logger;

    /**
     * Crée une instance de Database avec un connecteur.
     *
     * Configure automatiquement PDO pour lever des exceptions et utiliser le mode FETCH_OBJ.
     *
     * @param Connector $connector Le connecteur de base de données à utiliser
     * @param Logger|null $logger Logger optionnel pour enregistrer les requêtes SQL
     *
     * @example
     * ```php
     * $connector = new MySQLConnector('mydb', 'user', 'password');
     * $database = new Database($connector);
     * ```
     */
    public function __construct(
        private readonly Connector $connector,
        ?Logger                    $logger = null,
    )
    {
        $this->logger = $logger;
        $connector->connect();
        $this->getPDO()->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->getPDO()->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
    }

    private function getPDO(): PDO
    {
        return $this->connector->getInstance();
    }

    /**
     * Récupère le helper SQL associé au connecteur.
     *
     * Le helper permet de générer des déclarations SQL spécifiques au type de base de données.
     *
     * @return SQLHelper Le helper SQL (MySQLHelper, SQLiteHelper, etc.)
     *
     * @example
     * ```php
     * $helper = $database->getHelper();
     * $sql = $helper->getAutoincrement('id');
     * // Pour MySQL: "id INT AUTO_INCREMENT PRIMARY KEY"
     * // Pour SQLite: "id INTEGER PRIMARY KEY AUTOINCREMENT"
     * ```
     */
    public function getHelper(): SQLHelper
    {
        return $this->connector->getHelper();
    }

    /**
     * Ferme la connexion à la base de données.
     *
     * Libère les ressources et supprime la référence au logger.
     *
     * @return void
     *
     * @example
     * ```php
     * $database->close();
     * ```
     */
    public function close(): void
    {
        $this->connector->close();
        $this->logger = null;
    }

    /**
     * Exécute une requête SQL préparée.
     *
     * @param string $query La requête SQL avec des placeholders (?) pour les paramètres
     * @param array $params Les valeurs à lier aux placeholders
     * @return SQLStatement Un objet SQLStatement permettant de récupérer les résultats
     *
     * @example
     * ```php
     * $stmt = $database->execute('SELECT * FROM users WHERE id = ?', [123]);
     * $user = $stmt->fetch();
     * ```
     */
    public function execute(string $query, array $params = []): SQLStatement
    {
        $this->logger?->push('debug', 'SQL: ' . $query, $params);

        $stmt = $this->getPDO()->prepare($query);
        $stmt->execute($params);
        return new SQLStatement($stmt);
    }

    /**
     * Insère une nouvelle ligne dans une table.
     *
     * @param string $table Le nom de la table
     * @param array $values Tableau associatif des colonnes et valeurs à insérer
     * @return int L'ID de la ligne insérée (clé primaire auto-incrémentée)
     *
     * @example
     * ```php
     * $id = $database->insert('users', [
     *     'name' => 'John Doe',
     *     'email' => 'john@example.com',
     *     'created_at' => date('Y-m-d H:i:s')
     * ]);
     * // Retourne l'ID de la nouvelle ligne
     * ```
     */
    public function insert(string $table, array $values): int
    {
        $keys = implode(',', array_map(fn(string $key) => "`$key`", array_keys($values)));
        $marks = implode(',', array_fill(0, count($values), '?'));
        $this->execute("insert into $table ($keys) values ($marks)", array_values($values));
        return $this->getLastInsertId();
    }

    /**
     * Met à jour une ou plusieurs lignes dans une table.
     *
     * @param string $table Le nom de la table
     * @param array $values Tableau associatif des colonnes et nouvelles valeurs
     * @param string $pk Le nom de la colonne de clé primaire
     * @param mixed $pkValue La valeur de la clé primaire pour identifier la ligne
     * @return int Le nombre de lignes affectées
     *
     * @example
     * ```php
     * $rows = $database->update('users', [
     *     'name' => 'Jane Doe',
     *     'email' => 'jane@example.com'
     * ], 'id', 123);
     * // Retourne le nombre de lignes mises à jour (généralement 1)
     * ```
     */
    public function update(string $table, array $values, string $pk, mixed $pkValue): int
    {
        $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($values)));
        return $this->execute(
            "UPDATE $table SET $sets WHERE `$pk` = ?",
            [...array_values($values), $pkValue]
        )
            ->rowCount();
    }

    /**
     * Supprime une ligne d'une table par sa clé primaire.
     *
     * @param string $table Le nom de la table
     * @param string $pk Le nom de la colonne de clé primaire
     * @param mixed $pkValue La valeur de la clé primaire
     * @return int Le nombre de lignes supprimées (0 ou 1)
     *
     * @example
     * ```php
     * $rows = $database->delete('users', 'id', 123);
     * // Retourne 1 si la ligne a été supprimée, 0 sinon
     * ```
     */
    public function delete(string $table, string $pk, mixed $pkValue): int
    {
        return $this->execute("DELETE FROM $table WHERE `$pk` = ?", [$pkValue])->rowCount();
    }

    /**
     * Supprime des lignes selon des conditions multiples.
     *
     * @param string $table Le nom de la table
     * @param Conditions $keysValues Les conditions à appliquer
     * @param string $operator L'opérateur logique entre les conditions ('AND' ou 'OR')
     * @return int Le nombre de lignes supprimées
     *
     * @example
     * ```php
     * $conditions = new Conditions(
     *     new SQLCondition('status', 'inactive', Operator::Equals),
     *     new SQLCondition('created_at', '2020-01-01', Operator::LessThan)
     * );
     * $rows = $database->deleteByConditions('users', $conditions, 'AND');
     * ```
     */
    public function deleteByConditions(string $table, Conditions $keysValues, string $operator = 'AND'): int
    {
        $conditions = $this->buildConditions($keysValues, $operator);
        return $this->execute("DELETE FROM $table WHERE $conditions", $keysValues->getValues())->rowCount();
    }

    /**
     * Récupère l'ID de la dernière ligne insérée.
     *
     * @return int L'ID de la dernière ligne insérée (0 si aucune insertion n'a eu lieu)
     *
     * @example
     * ```php
     * $database->insert('users', ['name' => 'John']);
     * $id = $database->getLastInsertId();
     * // Retourne l'ID auto-généré de la ligne insérée
     * ```
     */
    public function getLastInsertId(): int
    {
        return $this->getPDO()->lastInsertId();
    }

    /**
     * Charge des lignes selon un critère simple.
     *
     * @param string $table Le nom de la table
     * @param string $key Le nom de la colonne
     * @param mixed $value La valeur à rechercher
     * @return SQLStatement Un objet SQLStatement pour récupérer les résultats
     *
     * @example
     * ```php
     * $stmt = $database->loadBy('users', 'email', 'john@example.com');
     * $user = $stmt->fetch();
     * // ou
     * $users = $stmt->fetchAll();
     * ```
     */
    public function loadBy(string $table, string $key, mixed $value): SQLStatement
    {
        return $this->execute("SELECT * FROM $table WHERE `$key` = ?", [$value]);
    }

    /**
     * Charge des lignes selon des conditions multiples.
     *
     * @param string $table Le nom de la table
     * @param Conditions $keysValues Les conditions à appliquer
     * @param string $cond L'opérateur logique entre les conditions ('AND' ou 'OR')
     * @return SQLStatement Un objet SQLStatement pour récupérer les résultats
     *
     * @example
     * ```php
     * $conditions = new Conditions(
     *     new SQLCondition('status', 'active', Operator::Equals),
     *     new SQLCondition('age', 18, Operator::GreaterThanOrEqual)
     * );
     * $stmt = $database->loadByConditions('users', $conditions, 'AND');
     * $users = $stmt->fetchAll();
     * ```
     */
    public function loadByConditions(string $table, Conditions $keysValues, string $cond = 'AND'): SQLStatement
    {
        $conditions = $this->buildConditions($keysValues, $cond);
        return $this->execute("SELECT * FROM $table WHERE $conditions", $keysValues->getValues());
    }

    // -- Utility
    private function buildConditions(Conditions $keysValues, string $cond = 'AND'): string
    {
        return implode(" $cond ",
            array_map(fn(SQLCondition $key) => "`$key->key` {$key->operator->value} ?", $keysValues->conditions)
        );
    }
}