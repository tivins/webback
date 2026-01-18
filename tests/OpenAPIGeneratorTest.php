<?php

declare(strict_types=1);

namespace Tivins\WebappTests;

use PHPUnit\Framework\TestCase;
use Tivins\Webapp\API;
use Tivins\Webapp\HTTPMethod;
use Tivins\Webapp\HTTPResponse;
use Tivins\Webapp\Request;
use Tivins\Webapp\RouteConfig;
use Tivins\WebappTests\classes\MockRoute;

class OpenAPIGeneratorTest extends TestCase
{
    public function testGenerateOpenAPISpecBasic(): void
    {
        $api = new API();
        $api->setRoutes([
            new RouteConfig('/users', MockRoute::class, HTTPMethod::GET),
        ]);

        $spec = $api->generateOpenAPISpec([
            'title' => 'Test API',
            'version' => '1.0.0',
        ]);

        // Vérifications de base
        self::assertEquals('3.0.3', $spec['openapi']);
        self::assertEquals('Test API', $spec['info']['title']);
        self::assertEquals('1.0.0', $spec['info']['version']);
        self::assertArrayHasKey('paths', $spec);
    }

    public function testGenerateOpenAPISpecWithPathParameter(): void
    {
        $api = new API();
        $api->setRoutes([
            new RouteConfig('/users/(\d+)', MockRoute::class, HTTPMethod::GET),
        ]);

        $spec = $api->generateOpenAPISpec();

        // Vérifier que la route avec paramètre est présente
        self::assertArrayHasKey('/users/{id}', $spec['paths']);
        self::assertArrayHasKey('get', $spec['paths']['/users/{id}']);
        
        $operation = $spec['paths']['/users/{id}']['get'];
        self::assertArrayHasKey('parameters', $operation);
        self::assertCount(1, $operation['parameters']);
        self::assertEquals('id', $operation['parameters'][0]['name']);
        self::assertEquals('integer', $operation['parameters'][0]['schema']['type']);
    }

    public function testGenerateOpenAPISpecWithBasePath(): void
    {
        $api = new API('/api/v1');
        $api->get('/users', MockRoute::class);

        $spec = $api->generateOpenAPISpec();

        // Le chemin ne doit pas inclure le basePath
        self::assertArrayHasKey('/users', $spec['paths']);
        self::assertArrayNotHasKey('/api/v1/users', $spec['paths']);
    }


    public function testGenerateOpenAPISpecWithDottedPath(): void
    {
        $api = new API('/api/v1');
        $api->get('/users.json', MockRoute::class);

        $spec = $api->generateOpenAPISpec();

        // Le chemin ne doit pas inclure le '.'
        self::assertArrayHasKey('/users.json', $spec['paths']);
    }

    public function testGroupMethodsByPath(): void
    {
        $api = new API();
        $api->get('/users/(\d+)', MockRoute::class);
        $api->setRoutes([
            new RouteConfig('/users/(\d+)', MockRoute::class, HTTPMethod::PUT),
        ]);
        $api->setRoutes([
            new RouteConfig('/users/(\d+)', MockRoute::class, HTTPMethod::DELETE),
        ]);

        $spec = $api->generateOpenAPISpec();

        // Un seul chemin avec trois méthodes
        self::assertArrayHasKey('/users/{id}', $spec['paths']);
        $path = $spec['paths']['/users/{id}'];
        self::assertArrayHasKey('get', $path);
        self::assertArrayHasKey('put', $path);
        self::assertArrayHasKey('delete', $path);
    }

    public function testGenerateOpenAPISpecWithPostMethod(): void
    {
        $api = new API();
        $api->setRoutes([
            new RouteConfig('/users', MockRoute::class, HTTPMethod::POST),
        ]);

        $spec = $api->generateOpenAPISpec();

        self::assertArrayHasKey('/users', $spec['paths']);
        self::assertArrayHasKey('post', $spec['paths']['/users']);
        
        $operation = $spec['paths']['/users']['post'];
        self::assertArrayHasKey('requestBody', $operation);
        self::assertArrayHasKey('responses', $operation);
        self::assertArrayHasKey('201', $operation['responses']); // Created
    }

    public function testGenerateOpenAPISpecWithServers(): void
    {
        $api = new API();
        $api->get('/users', MockRoute::class);

        $spec = $api->generateOpenAPISpec([
            'servers' => [
                ['url' => 'https://api.example.com', 'description' => 'Production'],
            ],
        ]);

        self::assertArrayHasKey('servers', $spec);
        self::assertCount(1, $spec['servers']);
        self::assertEquals('https://api.example.com', $spec['servers'][0]['url']);
    }

    public function testGenerateOpenAPISpecDefaultOptions(): void
    {
        $api = new API();
        $api->get('/users', MockRoute::class);

        $spec = $api->generateOpenAPISpec();

        // Vérifier les valeurs par défaut
        self::assertEquals('API Documentation', $spec['info']['title']);
        self::assertEquals('1.0.0', $spec['info']['version']);
        self::assertIsArray($spec['servers']);
    }

    public function testGenerateOpenAPISpecMultipleRoutes(): void
    {
        $api = new API();
        $api->setRoutes([
            new RouteConfig('/users', MockRoute::class, HTTPMethod::GET),
            new RouteConfig('/users/(\d+)', MockRoute::class, HTTPMethod::GET),
            new RouteConfig('/posts/(\d+)/comments', MockRoute::class, HTTPMethod::GET),
        ]);

        $spec = $api->generateOpenAPISpec();

        self::assertArrayHasKey('/users', $spec['paths']);
        self::assertArrayHasKey('/users/{id}', $spec['paths']);
        self::assertArrayHasKey('/posts/{id}/comments', $spec['paths']);
    }

    public function testGenerateOpenAPISpecOperationId(): void
    {
        $api = new API();
        $api->get('/users/(\d+)', MockRoute::class);

        $spec = $api->generateOpenAPISpec();

        $operation = $spec['paths']['/users/{id}']['get'];
        self::assertArrayHasKey('operationId', $operation);
        self::assertStringStartsWith('get_', $operation['operationId']);
    }

    // === Tests pour les callables ===

    public function testGenerateOpenAPISpecWithClosure(): void
    {
        $api = new API();
        $api->get('/health', fn(Request $req, array $matches) => new HTTPResponse(200, ['status' => 'ok']));

        $spec = $api->generateOpenAPISpec();

        self::assertArrayHasKey('/health', $spec['paths']);
        self::assertArrayHasKey('get', $spec['paths']['/health']);
    }

    public function testGenerateOpenAPISpecWithClosureAndDocBlock(): void
    {
        $api = new API();
        
        /**
         * Check the health status of the API.
         */
        $handler = fn(Request $req, array $matches) => new HTTPResponse(200, ['status' => 'ok']);
        $api->get('/health', $handler);

        $spec = $api->generateOpenAPISpec();

        // Note: Les PHPDoc sur les closures définies inline ne sont pas accessibles via ReflectionFunction
        // Ce test vérifie juste que la génération fonctionne avec des closures
        self::assertArrayHasKey('/health', $spec['paths']);
        self::assertArrayHasKey('get', $spec['paths']['/health']);
        self::assertArrayHasKey('operationId', $spec['paths']['/health']['get']);
    }

    public function testGenerateOpenAPISpecWithCallableArray(): void
    {
        $api = new API();
        $api->get('/test', [OpenAPICallableHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        self::assertArrayHasKey('/test', $spec['paths']);
        self::assertArrayHasKey('get', $spec['paths']['/test']);
        
        // Le PHPDoc de la méthode devrait être extrait
        $operation = $spec['paths']['/test']['get'];
        self::assertEquals('Handle the test request.', $operation['summary']);
    }

    public function testGenerateOpenAPISpecMixedHandlers(): void
    {
        $api = new API();
        $api->get('/users', MockRoute::class);
        $api->get('/health', fn($req, $m) => new HTTPResponse(200));
        $api->get('/test', [OpenAPICallableHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        self::assertArrayHasKey('/users', $spec['paths']);
        self::assertArrayHasKey('/health', $spec['paths']);
        self::assertArrayHasKey('/test', $spec['paths']);
    }
}

/**
 * Classe helper pour tester l'extraction de métadonnées des callable arrays.
 */
class OpenAPICallableHandler
{
    /**
     * Handle the test request.
     * 
     * This is a detailed description of the handler.
     */
    public static function handle(Request $request, array $matches): HTTPResponse
    {
        return new HTTPResponse(200, ['type' => 'callable_array']);
    }
}
