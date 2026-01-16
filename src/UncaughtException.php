<?php

declare(strict_types=1);

namespace Tivins\Webapp;

use Exception;

/**
 * Gestionnaire global des exceptions non capturées.
 *
 * Configure un gestionnaire d'exceptions qui affiche les erreurs
 * de manière formatée (JSON pour les requêtes API, HTML pour les autres).
 */
class UncaughtException
{
    /**
     * Initialise le gestionnaire d'exceptions global.
     *
     * Configure un gestionnaire qui intercepte toutes les exceptions non capturées
     * et génère une réponse HTTP appropriée :
     * - JSON pour les requêtes avec Accept: application/json
     * - HTML formaté pour les autres requêtes
     *
     * @return void
     *
     * @example
     * ```php
     * // À appeler au début de l'application
     * UncaughtException::init();
     * // Toutes les exceptions non capturées seront automatiquement gérées
     * ```
     */
    public static function init(): void
    {
        set_exception_handler(function (Exception $e) {
            $request = Request::fromHTTP();
            if ($request->accept == ContentType::JSON) {
                $body = [
                    'type' => get_class($e),
                    'trace' => $e->getTrace(),
                    'message' => $e->getMessage(),
                    'request' => $request,
                    'e' => $e,
                ];
                (new HTTPResponse(code: 500, body: $body, contentType: ContentType::JSON))->output();
            }
            $page = new HTMLDocument('Exception');
            $page->addStyle('
                *{font-family: monospace;}
                body{background:#222;color:#ccc;font-size:16px;}
                .container{background: #333;max-width: 840px;margin: 2rem auto;border:1px solid #933;padding:.5rem;border-radius: 5px;box-shadow: 0 0 1rem #111;}
                .pre{background: #222;padding:1rem;white-space: pre;border-radius: 5px;}
                .t1{background:#933;color:white;padding:2rem 1rem;}
                .t1 div{font-size:150%;}
                .t2{background:#630;padding:1rem;}
                .t3{border-left:5px solid #630;padding:1rem;font-style: italic;}
                ');
            $page->setBody(
                '<div class="container">'
                . '<div class="t1" >webapp v1.0<div>CAUGHT EXCEPTION</div></div>'
                . '<div class="t2">' . get_class($e) . '</div>'
                . '<div class="t3">' . $e->getMessage() . "</div>"
                . '<h4>Exception:</h4>'
                . '<div class="pre">' . json_encode($e, JSON_PRETTY_PRINT) . '</div>'
                . '<h4>Stack:</h4>'
                . '<div class="pre">' . json_encode($e->getTrace(), JSON_PRETTY_PRINT) . '</div>'
                . '<h4>Request:</h4>'
                . '<div class="pre">' . json_encode(Request::fromHTTP(), JSON_PRETTY_PRINT) . '</div>'
                . '</div>'
            );

            (new HTTPResponse(500, $page->render(), contentType: ContentType::HTML))->output();
        });
    }
}