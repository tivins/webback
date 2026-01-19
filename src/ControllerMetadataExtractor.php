<?php

declare(strict_types=1);

namespace Tivins\Webapp;

use ReflectionAttribute;
use ReflectionException;
use ReflectionMethod;

/**
 * Extrait les métadonnées depuis les handlers de routes (classes, closures, callables).
 *
 * Cette classe supporte deux sources de métadonnées :
 * 1. Les attributs PHP `#[RouteAttribute]` (prioritaires)
 * 2. Les commentaires PHPDoc (fallback)
 *
 * Les attributs RouteAttribute sont recherchés sur :
 * - La méthode `trigger()` pour les classes implémentant RouteInterface
 * - La méthode spécifiée pour les callable arrays [Class::class, 'method']
 * - Les closures ne supportent pas les attributs (PHPDoc utilisé)
 *
 * Le type de retour (`returnType`) est extrait selon l'ordre de priorité :
 * 1. `RouteAttribute->returnType` (explicite, prioritaire)
 * 2. PHPDoc `@return` avec parsing du type
 */
class ControllerMetadataExtractor
{
    /**
     * Extrait les métadonnées depuis un handler de route.
     *
     * Priorité d'extraction :
     * 1. Attribut RouteAttribute sur la méthode
     * 2. PHPDoc de la méthode/classe/closure
     *
     * @param string|\Closure|array $handler Le handler (nom de classe, closure, ou callable array)
     * @return array{summary: string, description: string, responses: array, contentType: ?ContentType, tags: array, deprecated: bool, operationId: string, returnType: string|array|null}
     */
    public function extract(string|\Closure|array $handler): array
    {
        // 1. Tenter d'extraire depuis RouteAttribute (prioritaire)
        $attributeMetadata = $this->extractFromAttribute($handler);
        if ($attributeMetadata !== null) {
            // Si returnType n'est pas défini dans l'attribut, essayer PHPDoc
            if (empty($attributeMetadata['returnType'])) {
                $docComment = $this->getDocComment($handler);
                $attributeMetadata['returnType'] = $this->extractReturnTypeFromDoc($docComment);
            }
            return $attributeMetadata;
        }

        // 2. Fallback sur PHPDoc
        $docComment = $this->getDocComment($handler);
        $returnType = $this->extractReturnTypeFromDoc($docComment);

        // 3. Si pas de returnType dans PHPDoc et que c'est une closure, analyser le code source
        if ($returnType === null && $handler instanceof \Closure) {
            $returnType = $this->extractReturnTypeFromClosureSource($handler);
        }

        return [
            'summary' => $this->extractSummaryFromDoc($docComment),
            'description' => $this->extractDescriptionFromDoc($docComment),
            'responses' => $this->extractResponsesFromDoc($docComment),
            'contentType' => null,
            'tags' => [],
            'deprecated' => false,
            'operationId' => '',
            'returnType' => $returnType,
        ];
    }

    /**
     * Extrait les métadonnées depuis un attribut RouteAttribute.
     *
     * Extrait d'abord les attributs de la classe, puis ceux de la méthode.
     * Les valeurs de la méthode surchargent celles de la classe.
     * Les tags sont fusionnés (tags de la méthode ajoutés à ceux de la classe).
     *
     * @param string|\Closure|array $handler Le handler
     * @return array|null Les métadonnées ou null si aucun attribut trouvé
     */
    private function extractFromAttribute(string|\Closure|array $handler): ?array
    {
        $reflectionMethod = $this->getReflectionMethod($handler);
        if ($reflectionMethod === null) {
            return null;
        }

        // 1. Extraire les attributs de la classe
        $classAttributes = [];
        $reflectionClass = $reflectionMethod->getDeclaringClass();
        $classRouteAttributes = $reflectionClass->getAttributes(
            RouteAttribute::class,
            ReflectionAttribute::IS_INSTANCEOF
        );
        if (!empty($classRouteAttributes)) {
            /** @var RouteAttribute $classAttribute */
            $classAttribute = $classRouteAttributes[0]->newInstance();
            $classAttributes = [
                'summary' => $classAttribute->name,
                'description' => $classAttribute->description,
                'contentType' => $classAttribute->contentType,
                'tags' => $classAttribute->tags,
                'deprecated' => $classAttribute->deprecated,
                'operationId' => $classAttribute->operationId,
                'returnType' => $classAttribute->returnType,
            ];
        }

        // 2. Extraire les attributs de la méthode
        $methodAttributes = [];
        $methodRouteAttributes = $reflectionMethod->getAttributes(
            RouteAttribute::class,
            ReflectionAttribute::IS_INSTANCEOF
        );
        if (!empty($methodRouteAttributes)) {
            /** @var RouteAttribute $methodAttribute */
            $methodAttribute = $methodRouteAttributes[0]->newInstance();
            $methodAttributes = [
                'summary' => $methodAttribute->name,
                'description' => $methodAttribute->description,
                'contentType' => $methodAttribute->contentType,
                'tags' => $methodAttribute->tags,
                'deprecated' => $methodAttribute->deprecated,
                'operationId' => $methodAttribute->operationId,
                'returnType' => $methodAttribute->returnType,
            ];
        }

        // Si aucun attribut trouvé (ni classe ni méthode), retourner null
        if (empty($classAttributes) && empty($methodAttributes)) {
            return null;
        }

        // 3. Fusionner : valeurs de la classe par défaut, surchargées par celles de la méthode
        // Pour returnType, la méthode a priorité, sinon la classe
        $returnType = !empty($methodAttributes['returnType'])
            ? $methodAttributes['returnType']
            : ($classAttributes['returnType'] ?? '');

        $merged = [
            'summary' => $methodAttributes['summary'] ?? $classAttributes['summary'] ?? '',
            'description' => $methodAttributes['description'] ?? $classAttributes['description'] ?? '',
            'contentType' => $methodAttributes['contentType'] ?? $classAttributes['contentType'] ?? ContentType::JSON,
            'tags' => array_unique(array_merge($classAttributes['tags'] ?? [], $methodAttributes['tags'] ?? [])),
            'deprecated' => $methodAttributes['deprecated'] ?? $classAttributes['deprecated'] ?? false,
            'operationId' => $methodAttributes['operationId'] ?? $classAttributes['operationId'] ?? '',
            'returnType' => $returnType,
            'responses' => [],
        ];

        return $merged;
    }

    /**
     * Obtient la ReflectionMethod pour un handler.
     *
     * @param string|\Closure|array $handler Le handler
     * @return ReflectionMethod|null La méthode de réflection ou null
     */
    private function getReflectionMethod(string|\Closure|array $handler): ?ReflectionMethod
    {
        // Cas 1: Nom de classe (string) - chercher la méthode trigger()
        if (is_string($handler)) {
            if (!class_exists($handler)) {
                return null;
            }
            try {
                return new ReflectionMethod($handler, 'trigger');
            } catch (ReflectionException) {
                return null;
            }
        }

        // Cas 2: Closure - pas de support des attributs
        if ($handler instanceof \Closure) {
            return null;
        }

        // Cas 3: Callable array [Class::class, 'method'] ou [$object, 'method']
        if (is_array($handler) && count($handler) === 2) {
            [$classOrObject, $method] = $handler;
            try {
                return new ReflectionMethod($classOrObject, $method);
            } catch (ReflectionException) {
                return null;
            }
        }

        return null;
    }

    /**
     * Récupère le PHPDoc selon le type de handler.
     *
     * @param string|\Closure|array $handler Le handler
     * @return string Le commentaire PHPDoc ou chaîne vide
     */
    private function getDocComment(string|\Closure|array $handler): string
    {
        // Cas 1: Nom de classe (string)
        if (is_string($handler)) {
            if (!class_exists($handler)) {
                return '';
            }
            $reflection = new \ReflectionClass($handler);
            return $reflection->getDocComment() ?: '';
        }

        // Cas 2: Closure
        if ($handler instanceof \Closure) {
            $reflection = new \ReflectionFunction($handler);
            return $reflection->getDocComment() ?: '';
        }

        // Cas 3: Callable array [Class::class, 'method'] ou [$object, 'method']
        if (is_array($handler) && count($handler) === 2) {
            [$classOrObject, $method] = $handler;
            try {
                $reflection = new \ReflectionMethod($classOrObject, $method);
                return $reflection->getDocComment() ?: '';
            } catch (\ReflectionException) {
                return '';
            }
        }

        return '';
    }

    /**
     * Extrait le résumé depuis le PHPDoc.
     * Prend la première ligne non vide du commentaire.
     */
    private function extractSummaryFromDoc(string $docComment): string
    {
        if (empty($docComment)) {
            return '';
        }

        $lines = explode("\n", $docComment);
        foreach ($lines as $line) {
            $line = trim($line);
            // Ignorer les balises de début/fin et les lignes vides
            if (empty($line) || $line === '/**' || $line === '*/' || str_starts_with($line, '* @')) {
                continue;
            }
            // Enlever le * au début si présent
            $line = preg_replace('/^\s*\*\s*/', '', $line);
            if (!empty($line)) {
                return $line;
            }
        }

        return '';
    }

    /**
     * Extrait la description complète depuis le PHPDoc.
     * Prend toutes les lignes jusqu'à la première annotation.
     */
    private function extractDescriptionFromDoc(string $docComment): string
    {
        if (empty($docComment)) {
            return '';
        }

        $lines = explode("\n", $docComment);
        $description = [];

        foreach ($lines as $line) {
            $line = trim($line);
            // Arrêter à la première annotation
            if (str_starts_with($line, '* @')) {
                break;
            }
            // Ignorer les balises de début/fin et les lignes vides
            if (empty($line) || $line === '/**' || $line === '*/') {
                continue;
            }
            // Enlever le * au début si présent
            $line = preg_replace('/^\s*\*\s*/', '', $line);
            if (!empty($line)) {
                $description[] = $line;
            }
        }

        return implode(' ', $description);
    }

    /**
     * Extrait les informations de réponse depuis le PHPDoc.
     * Pour l'instant, retourne un tableau vide (peut être enrichi plus tard).
     */
    private function extractResponsesFromDoc(string $docComment): array
    {
        // TODO: Parser les annotations @return ou @response dans le PHPDoc
        // Pour l'instant, on retourne un tableau vide
        return [];
    }

    /**
     * Extrait le type de retour depuis le PHPDoc.
     *
     * Parse l'annotation @return pour extraire le type de retour.
     * Supporte les formats :
     * - @return User
     * - @return User[]
     * - @return HTTPResponse<User>
     * - @return \App\Models\User
     *
     * @param string $docComment Le commentaire PHPDoc
     * @return string|null Le type de retour ou null si non trouvé
     */
    private function extractReturnTypeFromDoc(string $docComment): ?string
    {
        if (empty($docComment)) {
            return null;
        }

        // Pattern pour extraire @return type
        // Supporte: User, User[], HTTPResponse<User>, \Namespace\Class
        if (preg_match('/@return\s+(\S+)/', $docComment, $matches)) {
            $type = $matches[1];

            // Ignorer les types génériques PHP comme HTTPResponse (garder le type complet)
            // Si c'est HTTPResponse<Type>, extraire Type
            if (preg_match('/^HTTPResponse<(.+)>$/', $type, $genericMatches)) {
                return $genericMatches[1];
            }

            // Ignorer les types primitifs qui ne sont pas utiles pour les schémas
            $ignoredTypes = ['void', 'null', 'self', 'static', 'mixed', 'HTTPResponse'];
            if (in_array($type, $ignoredTypes, true)) {
                return null;
            }

            return $type;
        }

        return null;
    }

    /**
     * Extrait le type de retour depuis le code source d'une closure.
     *
     * Analyse le code source pour détecter les patterns `new HTTPResponse(...)` et
     * extraire les types d'objets instanciés dans le body (deuxième paramètre).
     * Supporte les cas simples comme :
     * - `new HTTPResponse(200, [new AnyObject()])`
     * - `new HTTPResponse(200, new AnyObject())`
     * - `return new HTTPResponse(200, [new AnyObject()]);`
     *
     * @param \Closure $closure La closure à analyser
     * @return string|null Le type de retour détecté (ex: 'AnyObject', 'AnyObject[]') ou null
     */
    private function extractReturnTypeFromClosureSource(\Closure $closure): ?string
    {
        try {
            $reflection = new \ReflectionFunction($closure);
            $filename = $reflection->getFileName();
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();

            if ($filename === false || $startLine === false || $endLine === false) {
                return null;
            }

            // Lire le fichier source
            $lines = file($filename);
            if ($lines === false) {
                return null;
            }

            // Extraire le namespace du fichier
            $namespace = $this->extractNamespaceFromFile($lines);

            // Extraire le code de la closure (lignes startLine à endLine)
            $sourceCode = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
            
            // Normaliser les espaces et retours à la ligne
            $sourceCode = preg_replace('/\s+/', ' ', $sourceCode);

            // Pattern pour détecter new HTTPResponse(code, body, ...)
            // On cherche le deuxième paramètre (le body) en comptant les parenthèses/crochets
            if (preg_match('/new\s+HTTPResponse\s*\(\s*\d+\s*,\s*(.+)/', $sourceCode, $matches)) {
                $bodyParam = $this->extractFirstParameter($matches[1]);
                
                if ($bodyParam === null) {
                    return null;
                }
                
                // Nettoyer les espaces supplémentaires
                $bodyParam = preg_replace('/\s+/', ' ', trim($bodyParam));

                // Cas 1: Tableau avec new ClassName() - [new AnyObject()]
                // Pattern amélioré pour gérer les espaces et les parenthèses
                if (preg_match('/\[\s*new\s+([a-zA-Z_][a-zA-Z0-9_\\\\]*)\s*\([^)]*\)\s*\]/', $bodyParam, $arrayMatches)) {
                    $className = $arrayMatches[1];
                    // Résoudre le namespace si nécessaire
                    $fullClassName = $this->resolveClassName($className, $namespace);
                    // Vérifier si c'est une classe valide
                    if ($this->isValidClassName($fullClassName)) {
                        return $fullClassName . '[]';
                    }
                }

                // Cas 2: Plusieurs objets dans un tableau - [new Class1(), new Class2()]
                // Détecter le premier objet dans le tableau
                if (preg_match('/\[\s*new\s+([a-zA-Z_][a-zA-Z0-9_\\\\]*)\s*\([^)]*\)/', $bodyParam, $arrayMatches)) {
                    $className = $arrayMatches[1];
                    $fullClassName = $this->resolveClassName($className, $namespace);
                    if ($this->isValidClassName($fullClassName)) {
                        return $fullClassName . '[]';
                    }
                }

                // Cas 3: Objet unique - new AnyObject()
                if (preg_match('/new\s+([a-zA-Z_][a-zA-Z0-9_\\\\]*)\s*\([^)]*\)/', $bodyParam, $objectMatches)) {
                    $className = $objectMatches[1];
                    $fullClassName = $this->resolveClassName($className, $namespace);
                    if ($this->isValidClassName($fullClassName)) {
                        return $fullClassName;
                    }
                }
            }
        } catch (\ReflectionException) {
            // En cas d'erreur de réflexion, retourner null
            return null;
        }

        return null;
    }

    /**
     * Extrait le premier paramètre d'une liste de paramètres, en gérant les parenthèses et crochets imbriqués.
     *
     * @param string $params La chaîne contenant les paramètres
     * @return string|null Le premier paramètre extrait ou null si erreur
     */
    private function extractFirstParameter(string $params): ?string
    {
        $params = trim($params);
        $result = '';
        $depth = 0;
        $bracketDepth = 0;
        $inString = false;
        $stringChar = null;
        $length = strlen($params);

        for ($i = 0; $i < $length; $i++) {
            $char = $params[$i];
            $prevChar = $i > 0 ? $params[$i - 1] : '';

            // Gérer les chaînes de caractères
            if (($char === '"' || $char === "'") && $prevChar !== '\\') {
                if (!$inString) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === $stringChar) {
                    $inString = false;
                    $stringChar = null;
                }
                $result .= $char;
                continue;
            }

            if ($inString) {
                $result .= $char;
                continue;
            }

            // Compter les parenthèses
            if ($char === '(') {
                $depth++;
                $result .= $char;
            } elseif ($char === ')') {
                $depth--;
                $result .= $char;
                // Si on revient à la profondeur 0 et qu'on est dans un paramètre, on s'arrête
                if ($depth === 0 && $bracketDepth === 0) {
                    // Vérifier s'il y a une virgule après
                    $remaining = trim(substr($params, $i + 1));
                    if (empty($remaining) || $remaining[0] === ',' || $remaining[0] === ')') {
                        return trim($result);
                    }
                }
            }
            // Compter les crochets
            elseif ($char === '[') {
                $bracketDepth++;
                $result .= $char;
            } elseif ($char === ']') {
                $bracketDepth--;
                $result .= $char;
                // Si on revient à la profondeur 0 et qu'on est dans un paramètre, on s'arrête
                if ($depth === 0 && $bracketDepth === 0) {
                    // Vérifier s'il y a une virgule après
                    $remaining = trim(substr($params, $i + 1));
                    if (empty($remaining) || $remaining[0] === ',' || $remaining[0] === ')') {
                        return trim($result);
                    }
                }
            }
            // Si on trouve une virgule au niveau 0, on s'arrête
            elseif ($char === ',' && $depth === 0 && $bracketDepth === 0) {
                return trim($result);
            } else {
                $result .= $char;
            }
        }

        // Si on arrive à la fin, retourner ce qu'on a
        return trim($result) ?: null;
    }

    /**
     * Extrait le namespace depuis les lignes d'un fichier.
     *
     * @param array $lines Les lignes du fichier
     * @return string|null Le namespace ou null si non trouvé
     */
    private function extractNamespaceFromFile(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+)\s*;/', $line, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    /**
     * Résout un nom de classe en ajoutant le namespace si nécessaire.
     *
     * @param string $className Le nom de la classe (peut être avec ou sans namespace)
     * @param string|null $namespace Le namespace du fichier
     * @return string Le nom complet de la classe
     */
    private function resolveClassName(string $className, ?string $namespace): string
    {
        // Si la classe a déjà un namespace (commence par \), retourner tel quel
        if (str_starts_with($className, '\\')) {
            return ltrim($className, '\\');
        }

        // Si la classe existe déjà avec ce nom, retourner tel quel
        if (class_exists($className)) {
            return $className;
        }

        // Si on a un namespace et que la classe n'existe pas, essayer avec le namespace
        if ($namespace !== null) {
            $fullClassName = $namespace . '\\' . $className;
            if (class_exists($fullClassName)) {
                return $fullClassName;
            }
        }

        // Sinon, retourner le nom tel quel (sera vérifié par isValidClassName)
        return $className;
    }

    /**
     * Vérifie si un nom de classe est valide (existe et n'est pas un type primitif).
     *
     * @param string $className Le nom de la classe à vérifier
     * @return bool True si la classe est valide
     */
    private function isValidClassName(string $className): bool
    {
        // Ignorer les types primitifs et les classes PHP standard
        $ignoredTypes = [
            'array', 'string', 'int', 'integer', 'float', 'double', 'bool', 'boolean',
            'object', 'mixed', 'null', 'void', 'self', 'static',
            'HTTPResponse', 'Request', 'Message', 'ContentType'
        ];

        if (in_array($className, $ignoredTypes, true)) {
            return false;
        }

        // Vérifier si la classe existe (avec ou sans namespace)
        // Essayer d'abord avec le nom tel quel
        if (class_exists($className)) {
            return true;
        }

        // Essayer avec le namespace global
        if (class_exists('\\' . $className)) {
            return true;
        }

        // Si la classe n'existe pas encore (peut être définie dans le même fichier),
        // on accepte les noms qui ressemblent à des classes (commencent par une majuscule)
        // et ne sont pas des types primitifs
        return preg_match('/^[A-Z][a-zA-Z0-9_\\\\]*$/', $className) === 1;
    }
}
