<?php

declare(strict_types=1);

namespace Tivins\Webapp;

use DateTime;

readonly class Request
{
    private array $tokeData;

    /**
     * Crée une nouvelle requête HTTP.
     *
     * @param HTTPMethod $method La méthode HTTP (GET, POST, PUT, DELETE, etc.)
     * @param string $path Le chemin de la requête (ex: '/api/users/123')
     * @param mixed $body Le corps de la requête (généralement un objet décodé depuis JSON)
     * @param mixed $bearerToken Le token Bearer d'authentification
     * @param ContentType $accept Le type de contenu accepté par le client
     * @param DateTime $requestTime L'heure de la requête
     *
     * @example
     * ```php
     * $request = new Request(
     *     method: HTTPMethod::POST,
     *     path: '/api/users',
     *     body: ['name' => 'John', 'email' => 'john@example.com'],
     *     bearerToken: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
     *     accept: ContentType::JSON
     * );
     * ```
     */
    public function __construct(
        public HTTPMethod  $method = HTTPMethod::GET,
        public string      $path = '/',
        public mixed       $body = null,
        public mixed       $bearerToken = '',
        public ContentType $accept = ContentType::JSON,
        public DateTime    $requestTime = new DateTime,
    )
    {
    }

    /**
     * Récupère les données du token JWT décodé.
     *
     * Le token est décodé et mis en cache lors du premier appel.
     * Retourne null si le token est invalide ou expiré.
     *
     * @return array|null Les données du payload JWT ou null si le token est invalide
     *
     * @example
     * ```php
     * $request = Request::fromHTTP();
     * $tokenData = $request->getTokenData();
     * if ($tokenData) {
     *     $userId = $tokenData['user_id'];
     * }
     * ```
     */
    public function getTokenData(): ?array
    {
        if (!isset($this->tokeData)) {
            $data = Token::tryDecode($this->bearerToken);
            if ($data !== false) {
                $this->tokeData = $data;
            }
        }
        return $this->tokeData ?? null;
    }

    /**
     * Crée une requête à partir des variables serveur HTTP.
     *
     * Extrait automatiquement la méthode, le chemin, le corps, le token Bearer,
     * le Content-Type accepté et l'heure de la requête depuis les variables
     * superglobales PHP ($_SERVER, etc.).
     *
     * @return Request Une instance de Request remplie avec les données HTTP
     *
     * @example
     * ```php
     * // Dans un contrôleur ou route handler
     * $request = Request::fromHTTP();
     * $method = $request->method; // HTTPMethod::GET, POST, etc.
     * $path = $request->path; // '/api/users/123'
     * $body = $request->body; // Objet décodé depuis JSON
     * ```
     */
    public static function fromHTTP(): Request
    {
        $headers = apache_request_headers();
        $accept = $headers['Accept'] ?? '';
        return new Request(
            method: HTTPMethod::tryFrom($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path: $_SERVER['REQUEST_URI'] ?? '/',
            body: json_decode(file_get_contents("php://input")),
            bearerToken: str_replace('Bearer ', '', $headers['Authorization'] ?? $headers['authorization'] ?? ''),
            accept: ContentType::tryFrom(substr($accept, 0, strpos($accept, ','))) ?? ContentType::JSON,
            requestTime: DateTime::createFromFormat('U', $_SERVER['REQUEST_TIME'] ?? time()),
        );
    }
}