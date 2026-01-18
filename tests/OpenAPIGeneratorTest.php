<?php

declare(strict_types=1);

namespace Tivins\WebappTests;

use PHPUnit\Framework\TestCase;
use Tivins\Webapp\API;
use Tivins\Webapp\HTTPMethod;
use Tivins\Webapp\RouteConfig;

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
}
