<?php

namespace Tivins\Webapp;

/**
 * Représente un ensemble de conditions SQL.
 *
 * Permet de construire des requêtes avec plusieurs conditions combinées
 * avec des opérateurs logiques (AND, OR).
 */
readonly class Conditions {
    public array $conditions;

    /**
     * Crée un ensemble de conditions SQL.
     *
     * @param Cond ...$databaseConditions Les conditions à combiner
     *
     * @example
     * ```php
     * $conditions = new Conditions(
     *     new Cond('status', 'active', Operator::Equals),
     *     new Cond('age', 18, Operator::GreaterThanOrEqual),
     *     new Cond('email', null, Operator::IsNotNull)
     * );
     * ```
     */
    public function __construct(
        Cond ...$databaseConditions
    ) {
        $this->conditions = $databaseConditions;
    }

    /**
     * Récupère toutes les valeurs des conditions.
     *
     * Utile pour lier les valeurs aux placeholders dans les requêtes préparées.
     *
     * @return array Tableau des valeurs des conditions dans l'ordre
     *
     * @example
     * ```php
     * $conditions = new Conditions(
     *     new Cond('name', 'John', Operator::Equals),
     *     new Cond('age', 25, Operator::Equals)
     * );
     * $values = $conditions->getValues();
     * // Retourne: ['John', 25]
     * ```
     */
    public function getValues(): array {
        return array_column($this->conditions, 'value');
    }
}
