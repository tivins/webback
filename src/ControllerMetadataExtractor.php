<?php

declare(strict_types=1);

namespace Tivins\Webapp;

/**
 * Extrait les métadonnées depuis les handlers de routes (classes, closures, callables).
 */
class ControllerMetadataExtractor
{
    /**
     * Extrait les métadonnées depuis un handler de route.
     *
     * @param string|\Closure|array $handler Le handler (nom de classe, closure, ou callable array)
     * @return array{summary: string, description: string, responses: array}
     */
    public function extract(string|\Closure|array $handler): array
    {
        $docComment = $this->getDocComment($handler);

        return [
            'summary' => $this->extractSummaryFromDoc($docComment),
            'description' => $this->extractDescriptionFromDoc($docComment),
            'responses' => $this->extractResponsesFromDoc($docComment),
        ];
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
}
