<?php

declare(strict_types=1);

namespace Tivins\Webapp;

/**
 * Convertit les patterns regex en chemins OpenAPI avec paramètres.
 */
class OpenAPIPathConverter
{
    /**
     * Noms de paramètres par défaut utilisés lors de la conversion.
     */
    private const array DEFAULT_PARAM_NAMES = ['id', 'param1', 'param2', 'param3', 'slug', 'query'];

    /**
     * Convertit un pattern regex en chemin OpenAPI avec paramètres.
     *
     * @param string $regexPattern Le pattern regex (ex: '/users/(\d+)')
     * @return array{path: string, parameters: array} Le chemin OpenAPI et les paramètres
     */
    public function convert(string $regexPattern): array
    {
        $parameters = [];
        $openApiPath = $regexPattern;
        $paramIndex = 0;

        // Trouver tous les groupes de capture
        preg_match_all('/\(([^)]+)\)/', $regexPattern, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            // Pas de groupes de capture, nettoyer le chemin
            $openApiPath = $this->cleanPath($regexPattern);
            return [
                'path' => $openApiPath,
                'parameters' => [],
            ];
        }

        // Construire un tableau avec les informations nécessaires
        $captures = [];
        foreach ($matches[0] as $index => $fullMatch) {
            $pattern = $matches[1][$index][0];
            $type = $this->inferOpenAPIType($pattern);
            $paramName = self::DEFAULT_PARAM_NAMES[$paramIndex] ?? "param$paramIndex";
            $paramIndex++;

            $captures[] = [
                'full' => $fullMatch[0],  // Ex: '(\d+)'
                'pattern' => $pattern, // Ex: '\d+'
                'offset' => $fullMatch[1], // Position dans la chaîne
                'paramName' => $paramName,
                'type' => $type,
            ];

            // Créer le paramètre OpenAPI dans l'ordre
            $parameters[] = [
                'name' => $paramName,
                'in' => 'path',
                'required' => true,
                'description' => "Path parameter",
                'schema' => [
                    'type' => $type,
                ],
            ];
        }

        // Remplacer dans le chemin en ordre inverse pour préserver les positions
        $capturesReversed = array_reverse($captures);
        foreach ($capturesReversed as $capture) {
            $fullMatch = $capture['full'];
            $offset = $capture['offset'];
            $paramName = $capture['paramName'];

            // Remplacer dans le chemin
            $openApiPath = substr_replace(
                $openApiPath,
                "{{$paramName}}",
                $offset,
                strlen($fullMatch)
            );
        }

        // Nettoyer le chemin (échapper les caractères spéciaux regex restants)
        $openApiPath = $this->cleanPath($openApiPath);

        return [
            'path' => $openApiPath,
            'parameters' => $parameters,
        ];
    }

    /**
     * Infère le type OpenAPI depuis un pattern regex.
     */
    private function inferOpenAPIType(string $pattern): string
    {
        // Patterns pour integer
        if (preg_match('/^\\\d\+$|^\[0-9\]\+$|^\d\+$/', $pattern)) {
            return 'integer';
        }

        // Patterns pour string (par défaut)
        return 'string';
    }

    /**
     * Nettoie le chemin en échappant les caractères spéciaux regex.
     * Préserve les paramètres OpenAPI {nom}.
     */
    private function cleanPath(string $path): string
    {
        // Protéger les paramètres OpenAPI {nom} avant le nettoyage
        $path = preg_replace_callback('/\{([^}]+)\}/', function ($matches) {
            return '___OPENAPI_PARAM___' . $matches[1] . '___END___';
        }, $path);

        // Remplacer les caractères spéciaux regex par des caractères normaux
        // Note: on préserve le point (.) car il est valide dans les chemins OpenAPI
        $path = preg_replace('/[*+?^$()|[\]\\\]/', '', $path);

        // Restaurer les paramètres OpenAPI
        $path = preg_replace('/___OPENAPI_PARAM___([^_]+)___END___/', '{$1}', $path);

        // S'assurer que le chemin commence par '/'
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        return $path;
    }
}
