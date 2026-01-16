<?php

namespace Tivins\Webapp;

/**
 * Implémentation de Logger utilisant error_log() d'Apache.
 *
 * Enregistre les messages dans les logs d'erreur d'Apache via error_log().
 * Les données supplémentaires sont encodées en JSON.
 */
class LoggerApache implements Logger
{
    /**
     * Enregistre un message dans les logs Apache.
     *
     * @param string $severity Le niveau de sévérité
     * @param string $message Le message à enregistrer
     * @param mixed $data Données supplémentaires (encodées en JSON)
     * @return void
     *
     * @example
     * ```php
     * $logger = new LoggerApache();
     * $logger->push('debug', 'SQL: SELECT * FROM users', ['params' => [123]]);
     * // Enregistre dans les logs Apache:
     * // [debug] SQL: SELECT * FROM users
     * //     {"params":[123]}
     * ```
     */
    public function push(string $severity, string $message, mixed $data = null): void
    {
        error_log("[$severity] $message");
        if ($data !== null) {
            error_log("\t" . json_encode($data));
        }
    }
}