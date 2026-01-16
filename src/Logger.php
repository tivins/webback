<?php

namespace Tivins\Webapp;

/**
 * Interface pour les loggers.
 *
 * Permet d'enregistrer des messages avec différents niveaux de sévérité.
 */
interface Logger
{
    /**
     * Enregistre un message dans les logs.
     *
     * @param string $severity Le niveau de sévérité (ex: 'debug', 'info', 'warning', 'error')
     * @param string $message Le message à enregistrer
     * @param mixed $data Données supplémentaires optionnelles à enregistrer
     * @return void
     *
     * @example
     * ```php
     * $logger->push('error', 'Échec de la connexion', ['host' => 'localhost', 'port' => 3306]);
     * ```
     */
    public function push(string $severity, string $message, mixed $data = null): void;
}