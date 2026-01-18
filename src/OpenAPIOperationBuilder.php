<?php

declare(strict_types=1);

namespace Tivins\Webapp;

/**
 * Construit les objets d'opération OpenAPI.
 */
class OpenAPIOperationBuilder
{
    /**
     * Construit une opération OpenAPI complète.
     *
     * @param string $method La méthode HTTP (GET, POST, etc.)
     * @param array $route Les informations de la route
     * @param array $parameters Les paramètres de chemin OpenAPI
     * @param array $metadata Les métadonnées extraites du contrôleur
     * @return array L'opération OpenAPI
     */
    public function build(
        string $method,
        array $route,
        array $parameters,
        array $metadata
    ): array {
        // Utiliser operationId depuis les métadonnées ou le générer
        $operationId = !empty($metadata['operationId'])
            ? $metadata['operationId']
            : $this->generateOperationId($route['regex'], $method);

        // Déterminer le contentType pour les réponses
        $contentType = $metadata['contentType'] ?? null;

        $operation = [
            'summary' => $metadata['summary'] ?: ucfirst(strtolower($method)) . ' operation',
            'description' => $metadata['description'] ?? '',
            'operationId' => $operationId,
            'responses' => $metadata['responses'] ?: $this->getDefaultResponses($method, $contentType),
        ];

        // Ajouter les tags si présents
        if (!empty($metadata['tags'])) {
            $operation['tags'] = $metadata['tags'];
        }

        // Ajouter deprecated si true
        if (!empty($metadata['deprecated'])) {
            $operation['deprecated'] = true;
        }

        // Ajouter les paramètres de chemin
        if (!empty($parameters)) {
            $operation['parameters'] = $parameters;
        }

        // Ajouter requestBody pour POST/PUT/PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $operation['requestBody'] = $this->buildRequestBody($route, $metadata);
        }

        return $operation;
    }

    /**
     * Génère un operationId unique depuis le pattern et la méthode.
     */
    private function generateOperationId(string $pattern, string $method): string
    {
        // Nettoyer le pattern
        $clean = preg_replace('/[^a-zA-Z0-9\/]/', '_', $pattern);
        $clean = trim($clean, '/_');
        $clean = str_replace('/', '_', $clean);

        return strtolower($method) . '_' . $clean;
    }

    /**
     * Retourne les réponses par défaut selon la méthode HTTP.
     *
     * @param string $method La méthode HTTP
     * @param ContentType|null $contentType Le type de contenu (null = application/json)
     */
    private function getDefaultResponses(string $method, ?ContentType $contentType = null): array
    {
        $mimeType = $this->contentTypeToMime($contentType);

        $responses = [
            '200' => [
                'description' => 'Success',
                'content' => [
                    $mimeType => [
                        'schema' => $this->getSchemaForContentType($contentType),
                    ],
                ],
            ],
            '404' => [
                'description' => 'Not Found',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'messages' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'object'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '500' => [
                'description' => 'Internal Server Error',
            ],
        ];

        // Pour POST/PUT, ajouter 201 Created
        if (in_array($method, ['POST', 'PUT'], true)) {
            $responses['201'] = [
                'description' => 'Created',
                'content' => [
                    $mimeType => [
                        'schema' => $this->getSchemaForContentType($contentType),
                    ],
                ],
            ];
        }

        return $responses;
    }

    /**
     * Convertit un ContentType en type MIME OpenAPI.
     */
    private function contentTypeToMime(?ContentType $contentType): string
    {
        if ($contentType === null || $contentType === ContentType::AUTO) {
            return 'application/json';
        }

        // ContentType::value contient déjà le type MIME
        return $contentType->value;
    }

    /**
     * Retourne le schéma OpenAPI approprié pour un ContentType.
     */
    private function getSchemaForContentType(?ContentType $contentType): array
    {
        if ($contentType === null || $contentType === ContentType::AUTO || $contentType === ContentType::JSON) {
            return ['type' => 'object'];
        }

        // Pour les types textuels (HTML, XML, CSV, TEXT), utiliser string
        return ['type' => 'string'];
    }

    /**
     * Construit le requestBody pour les méthodes POST/PUT/PATCH.
     */
    private function buildRequestBody(array $route, array $metadata): array
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        // Pourrait être enrichi depuis les métadonnées
                    ],
                ],
            ],
        ];
    }
}
