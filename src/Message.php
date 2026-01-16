<?php

namespace Tivins\Webapp;

/**
 * Représente un message dans une réponse HTTP.
 *
 * Utilisé pour inclure des messages d'information, de succès, d'avertissement
 * ou d'erreur dans les réponses de l'API.
 */
readonly class Message
{
    /**
     * Crée un nouveau message.
     *
     * @param string $text Le texte du message
     * @param MessageType $type Le type de message (Success, Error, Info, Warning)
     *
     * @example
     * ```php
     * $message = new Message('Utilisateur créé avec succès', MessageType::Success);
     * $response = new HTTPResponse(200, [], [$message]);
     * ```
     */
    public function __construct(
        public string      $text,
        public MessageType $type,
    )
    {
    }
}