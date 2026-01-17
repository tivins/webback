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
     * @param SQLCondition ...$databaseConditions Les conditions à combiner
     *
     * @example
     * ```php
     * $conditions = new Conditions(
     *     new SQLCondition('status', 'active', Operator::Equals),
     *     new SQLCondition('age', 18, Operator::GreaterThanOrEqual),
     *     new SQLCondition('email', null, Operator::IsNotNull)
     * );
     * ```
     */
    public function __construct(
        SQLCondition ...$databaseConditions
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
     *     new SQLCondition('name', 'John', Operator::Equals),
     *     new SQLCondition('age', 25, Operator::Equals)
     * );
     * $values = $conditions->getValues();
     * // Retourne: ['John', 25]
     * ```
     */
    public function getValues(): array {
        return array_column($this->conditions, 'value');
    }
}
