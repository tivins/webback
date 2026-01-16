<?php

namespace Tivins\Webapp;

/**
 * Représente une condition SQL individuelle.
 *
 * Une condition est composée d'une clé (nom de colonne), d'une valeur
 * et d'un opérateur de comparaison.
 */
readonly class Cond {
    /**
     * Crée une nouvelle condition SQL.
     *
     * @param string $key Le nom de la colonne
     * @param string $value La valeur à comparer
     * @param Operator $operator L'opérateur de comparaison (par défaut: Operator::Equals)
     *
     * @example
     * ```php
     * // Condition simple
     * $cond = new Cond('status', 'active', Operator::Equals);
     *
     * // Condition avec opérateur différent
     * $cond = new Cond('age', 18, Operator::GreaterThanOrEqual);
     *
     * // Condition de nullité
     * $cond = new Cond('deleted_at', null, Operator::IsNull);
     * ```
     */
    public function __construct(
        public string $key,
        public string $value,
        public Operator $operator = Operator::Equals,
    ) { }
}
