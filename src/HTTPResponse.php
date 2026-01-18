<?php

declare(strict_types=1);

namespace Tivins\Webapp;

readonly class HTTPResponse
{
    /**
     * Crée une nouvelle réponse HTTP.
     *
     * @param int $code Le code de statut HTTP (200, 404, 500, etc.)
     * @param array|string|object|null $body Le corps de la réponse (tableau pour JSON, string pour autres types)
     * @param array $messages Tableau de messages (erreurs, succès, etc.)
     * @param ContentType $contentType Le type de contenu de la réponse
     *
     * @example
     * ```php
     * // Réponse JSON avec succès
     * $response = new HTTPResponse(
     *     code: 200,
     *     body: ['id' => 123, 'name' => 'John'],
     *     messages: [new Message('Utilisateur créé', MessageType::Success)]
     * );
     *
     * // Réponse d'erreur
     * $response = new HTTPResponse(
     *     code: 404,
     *     messages: [new Message('Ressource non trouvée', MessageType::Error)]
     * );
     * ```
     */
    public function __construct(
        public int          $code = 200,
        public array|string|object|null $body = null,
        public array $messages = [],
        public ContentType  $contentType = ContentType::JSON,
    )
    {
    }

    /**
     * Envoie la réponse HTTP au client.
     *
     * Définit les en-têtes HTTP appropriés, le code de statut, et envoie
     * le corps de la réponse. Par défaut, termine l'exécution du script.
     *
     * @param bool $exit Si true, termine l'exécution du script après l'envoi
     * @return void
     *
     * @example
     * ```php
     * $response = new HTTPResponse(200, ['status' => 'ok']);
     * $response->output(); // Envoie la réponse et termine le script
     *
     * // Pour continuer l'exécution après l'envoi
     * $response->output(false);
     * ```
     */
    public function output(bool $exit = true): void
    {
        header('Content-type: ' . $this->contentType->value);
        http_response_code($this->code);
        echo match ($this->contentType) {
            ContentType::JSON => json_encode($this->body),
            default => $this->body,
        };
        if ($exit) {
            exit(0);
        }
    }
}