<?php

declare(strict_types=1);

namespace Tivins\Webapp;

/**
 * Construit les objets d'opération OpenAPI.
 *
 * Cette classe est responsable de la construction des opérations OpenAPI
 * à partir des métadonnées des routes. Elle utilise `OpenAPISchemaBuilder`
 * pour générer les schémas des réponses à partir des types de retour.
 */
class OpenAPIOperationBuilder
{
    /**
     * @param OpenAPISchemaBuilder|null $schemaBuilder Le constructeur de schémas (optionnel pour la rétrocompatibilité)
     */
    public function __construct(
        private ?OpenAPISchemaBuilder $schemaBuilder = null
    ) {
        // Créer une instance par défaut si non fournie
        $this->schemaBuilder ??= new OpenAPISchemaBuilder();
    }

    /**
     * Retourne le constructeur de schémas.
     *
     * Permet à `OpenAPIGenerator` d'accéder aux schémas générés
     * pour les ajouter à la section `components/schemas`.
     *
     * @return OpenAPISchemaBuilder
     */
    public function getSchemaBuilder(): OpenAPISchemaBuilder
    {
        return $this->schemaBuilder;
    }

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

        // Extraire le type de retour pour générer les schémas
        $returnType = $metadata['returnType'] ?? null;

        $operation = [
            'summary' => $metadata['summary'] ?: ucfirst(strtolower($method)) . ' operation',
            'description' => $metadata['description'] ?? '',
            'operationId' => $operationId,
            'responses' => $metadata['responses'] ?: $this->getDefaultResponses($method, $contentType, $returnType),
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
     * @param string|array|null $returnType Le type de retour pour générer le schéma
     */
    private function getDefaultResponses(
        string $method,
        ?ContentType $contentType = null,
        string|array|null $returnType = null
    ): array {
        $mimeType = $this->contentTypeToMime($contentType);

        // Si returnType est un tableau de mapping code => type, traiter spécialement
        if (is_array($returnType)) {
            return $this->buildResponsesFromMapping($returnType, $mimeType);
        }

        $schema = $this->getSchemaForContentType($contentType, $returnType);

        $responses = [
            '200' => [
                'description' => 'Success',
                'content' => [
                    $mimeType => [
                        'schema' => $schema,
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
                        'schema' => $schema,
                    ],
                ],
            ];
        }

        return $responses;
    }

    /**
     * Construit les réponses depuis un mapping code HTTP => type.
     *
     * @param array $mapping Le mapping code => type (ex: ['200' => 'User', '404' => 'Error'])
     * @param string $mimeType Le type MIME à utiliser
     * @return array Les réponses OpenAPI
     */
    private function buildResponsesFromMapping(array $mapping, string $mimeType): array
    {
        $responses = [];

        foreach ($mapping as $code => $type) {
            $description = match ((string)$code) {
                '200' => 'Success',
                '201' => 'Created',
                '204' => 'No Content',
                '400' => 'Bad Request',
                '401' => 'Unauthorized',
                '403' => 'Forbidden',
                '404' => 'Not Found',
                '500' => 'Internal Server Error',
                default => 'Response',
            };

            $responses[(string)$code] = [
                'description' => $description,
                'content' => [
                    $mimeType => [
                        'schema' => $this->buildSchemaFromReturnType($type),
                    ],
                ],
            ];
        }

        // Ajouter les réponses par défaut si non définies
        if (!isset($responses['500'])) {
            $responses['500'] = ['description' => 'Internal Server Error'];
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
     *
     * @param ContentType|null $contentType Le type de contenu
     * @param string|null $returnType Le type de retour (classe Mappable, tableau, etc.)
     * @return array Le schéma OpenAPI
     */
    private function getSchemaForContentType(?ContentType $contentType, ?string $returnType = null): array
    {
        if ($contentType === null || $contentType === ContentType::AUTO || $contentType === ContentType::JSON) {
            // Si un type de retour est spécifié, générer le schéma correspondant
            if ($returnType !== null && $returnType !== '') {
                return $this->buildSchemaFromReturnType($returnType);
            }
            // Par défaut, utiliser 'object' (compatibilité ascendante)
            return ['type' => 'object'];
        }

        // Pour les types textuels (HTML, XML, CSV, TEXT), utiliser string
        return ['type' => 'string'];
    }

    /**
     * Construit un schéma OpenAPI depuis un type de retour.
     *
     * Utilise `OpenAPISchemaBuilder` pour générer le schéma approprié.
     *
     * @param string $returnType Le type de retour (ex: 'User', 'User[]', '\App\Models\User')
     * @return array Le schéma OpenAPI
     */
    private function buildSchemaFromReturnType(string $returnType): array
    {
        return $this->schemaBuilder->buildFromTypeName($returnType);
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
