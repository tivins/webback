<?php

declare(strict_types=1);

namespace Tivins\Webapp;

use DateTime;

readonly class Request
{
    private array $tokenData;

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
     * @return array|null Payload JWT décodé, null si invalide. Cache au premier appel.
     */
    public function getTokenData(): ?array
    {
        if (!isset($this->tokenData)) {
            $data = Token::tryDecode($this->bearerToken);
            if ($data !== false) {
                $this->tokenData = $data;
            }
        }
        return $this->tokenData ?? null;
    }

    /**
     * Parse les superglobales PHP ($_SERVER, headers, php://input).
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
            requestTime: DateTime::createFromFormat('U', (string) ($_SERVER['REQUEST_TIME'] ?? time())),
        );
    }
}