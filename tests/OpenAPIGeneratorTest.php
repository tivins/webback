<?php

declare(strict_types=1);

namespace Tivins\WebappTests;

use PHPUnit\Framework\TestCase;
use Tivins\Webapp\API;
use Tivins\Webapp\ContentType;
use Tivins\Webapp\HTTPMethod;
use Tivins\Webapp\HTTPResponse;
use Tivins\Webapp\Request;
use Tivins\Webapp\RouteAttribute;
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

    // === Tests pour RouteAttribute ===

    public function testRouteAttributeMetadataExtraction(): void
    {
        $api = new API();
        $api->get('/users', MockRoute::class);

        $spec = $api->generateOpenAPISpec();

        $operation = $spec['paths']['/users']['get'];

        // Les métadonnées doivent venir de RouteAttribute
        self::assertEquals('Test route', $operation['summary']);
        self::assertEquals('Test route used for testing', $operation['description']);
        self::assertEquals(['Testing'], $operation['tags']);
    }

    public function testRouteAttributeWithTags(): void
    {
        $api = new API();
        $api->get('/tagged', [RouteAttributeTaggedHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        $operation = $spec['paths']['/tagged']['get'];
        self::assertArrayHasKey('tags', $operation);
        self::assertEquals(['Users', 'Admin'], $operation['tags']);
    }

    public function testRouteAttributeWithDeprecated(): void
    {
        $api = new API();
        $api->get('/deprecated', [RouteAttributeDeprecatedHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        $operation = $spec['paths']['/deprecated']['get'];
        self::assertArrayHasKey('deprecated', $operation);
        self::assertTrue($operation['deprecated']);
    }

    public function testRouteAttributeWithCustomOperationId(): void
    {
        $api = new API();
        $api->get('/custom-op', [RouteAttributeCustomOperationIdHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        $operation = $spec['paths']['/custom-op']['get'];
        self::assertEquals('myCustomOperationId', $operation['operationId']);
    }

    public function testRouteAttributeWithHtmlContentType(): void
    {
        $api = new API();
        $api->get('/html', [RouteAttributeHtmlHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        $operation = $spec['paths']['/html']['get'];
        self::assertArrayHasKey('text/html', $operation['responses']['200']['content']);
    }

    public function testRouteAttributePriorityOverPhpDoc(): void
    {
        $api = new API();
        $api->get('/priority', [RouteAttributeWithPhpDocHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        $operation = $spec['paths']['/priority']['get'];
        // L'attribut doit avoir priorité sur le PHPDoc
        self::assertEquals('From Attribute', $operation['summary']);
        self::assertEquals('Attribute description', $operation['description']);
    }

    // === Tests pour RouteAttribute sur les classes ===

    public function testRouteAttributeOnClass(): void
    {
        $api = new API();
        $api->get('/class-tagged', [RouteAttributeClassTaggedHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        $operation = $spec['paths']['/class-tagged']['get'];
        // Le tag de la classe doit être présent
        self::assertEquals(['Users'], $operation['tags']);
    }

    public function testRouteAttributeClassAndMethodMerge(): void
    {
        $api = new API();
        $api->get('/merged', [RouteAttributeClassAndMethodHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        $operation = $spec['paths']['/merged']['get'];
        // Les tags de la classe et de la méthode doivent être fusionnés
        self::assertEquals(['Users', 'Admin'], $operation['tags']);
        // Le name de la méthode doit surcharger celui de la classe
        self::assertEquals('Method name', $operation['summary']);
        // La description de la méthode doit surcharger celle de la classe
        self::assertEquals('Method description', $operation['description']);
    }

    public function testRouteAttributeClassOnly(): void
    {
        $api = new API();
        $api->get('/class-only', [RouteAttributeClassOnlyHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        $operation = $spec['paths']['/class-only']['get'];
        // Les métadonnées de la classe doivent être utilisées
        self::assertEquals(['Users'], $operation['tags']);
        self::assertEquals('Class name', $operation['summary']);
        self::assertEquals('Class description', $operation['description']);
    }

    // === Tests Phase 2 : Intégration avec les schémas Mappable ===

    public function testReturnTypeMappableGeneratesRefSchema(): void
    {
        $api = new API();
        $api->get('/users/(\d+)', [RouteAttributeWithReturnTypeHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        $operation = $spec['paths']['/users/{id}']['get'];

        // Vérifier que la réponse 200 utilise une référence $ref
        self::assertArrayHasKey('responses', $operation);
        self::assertArrayHasKey('200', $operation['responses']);
        $schema = $operation['responses']['200']['content']['application/json']['schema'];
        self::assertArrayHasKey('$ref', $schema);
        self::assertEquals('#/components/schemas/TestUserMappable', $schema['$ref']);
    }

    public function testReturnTypeMappableGeneratesComponentsSchemas(): void
    {
        $api = new API();
        $api->get('/users/(\d+)', [RouteAttributeWithReturnTypeHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        // Vérifier que la section components/schemas est présente
        self::assertArrayHasKey('components', $spec);
        self::assertArrayHasKey('schemas', $spec['components']);
        self::assertArrayHasKey('TestUserMappable', $spec['components']['schemas']);

        // Vérifier le contenu du schéma
        $userSchema = $spec['components']['schemas']['TestUserMappable'];
        self::assertEquals('object', $userSchema['type']);
        self::assertArrayHasKey('properties', $userSchema);
        self::assertArrayHasKey('id', $userSchema['properties']);
        self::assertArrayHasKey('name', $userSchema['properties']);
        self::assertArrayHasKey('email', $userSchema['properties']);
        // Vérifier les types (les descriptions PHPDoc peuvent être présentes)
        self::assertEquals('integer', $userSchema['properties']['id']['type']);
        self::assertEquals('string', $userSchema['properties']['name']['type']);
        self::assertEquals('string', $userSchema['properties']['email']['type']);
    }

    public function testReturnTypeArrayOfMappable(): void
    {
        $api = new API();
        $api->get('/users', [RouteAttributeWithArrayReturnTypeHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        $operation = $spec['paths']['/users']['get'];

        // Vérifier que la réponse 200 utilise un schéma array avec $ref
        $schema = $operation['responses']['200']['content']['application/json']['schema'];
        self::assertEquals('array', $schema['type']);
        self::assertArrayHasKey('items', $schema);
        self::assertArrayHasKey('$ref', $schema['items']);
        self::assertEquals('#/components/schemas/TestUserMappable', $schema['items']['$ref']);
    }

    public function testReturnTypeMappedByStatusCode(): void
    {
        $api = new API();
        $api->get('/articles/(\d+)', [RouteAttributeWithMappedReturnTypeHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        $operation = $spec['paths']['/articles/{id}']['get'];

        // Vérifier les réponses mappées par code HTTP
        self::assertArrayHasKey('200', $operation['responses']);
        self::assertArrayHasKey('404', $operation['responses']);

        // 200 -> TestArticleMappable
        $schema200 = $operation['responses']['200']['content']['application/json']['schema'];
        self::assertArrayHasKey('$ref', $schema200);
        self::assertEquals('#/components/schemas/TestArticleMappable', $schema200['$ref']);

        // 404 -> object
        $schema404 = $operation['responses']['404']['content']['application/json']['schema'];
        self::assertEquals(['type' => 'object'], $schema404);
    }

    public function testReturnTypeMappableWithDateTimeProperty(): void
    {
        $api = new API();
        $api->get('/articles/(\d+)', [RouteAttributeWithMappedReturnTypeHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        // Vérifier le schéma TestArticleMappable dans components
        self::assertArrayHasKey('TestArticleMappable', $spec['components']['schemas']);
        $articleSchema = $spec['components']['schemas']['TestArticleMappable'];

        // Vérifier que created_at est formaté en date-time
        self::assertArrayHasKey('created_at', $articleSchema['properties']);
        self::assertEquals('string', $articleSchema['properties']['created_at']['type']);
        self::assertEquals('date-time', $articleSchema['properties']['created_at']['format']);
    }

    public function testNoReturnTypeUsesDefaultObjectSchema(): void
    {
        $api = new API();
        $api->get('/users', MockRoute::class);

        $spec = $api->generateOpenAPISpec();

        $operation = $spec['paths']['/users']['get'];

        // Sans returnType, le schéma par défaut est 'object'
        $schema = $operation['responses']['200']['content']['application/json']['schema'];
        self::assertEquals(['type' => 'object'], $schema);

        // Pas de section components/schemas si aucun Mappable n'est référencé
        self::assertArrayNotHasKey('components', $spec);
    }

    public function testMultipleRoutesShareSchemas(): void
    {
        $api = new API();
        $api->get('/users', [RouteAttributeWithArrayReturnTypeHandler::class, 'handle']);
        $api->get('/users/(\d+)', [RouteAttributeWithReturnTypeHandler::class, 'handle']);

        $spec = $api->generateOpenAPISpec();

        // Les deux routes référencent TestUserMappable, il ne doit y avoir qu'un seul schéma
        self::assertArrayHasKey('components', $spec);
        self::assertCount(1, $spec['components']['schemas']);
        self::assertArrayHasKey('TestUserMappable', $spec['components']['schemas']);
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

/**
 * Handler avec RouteAttribute et tags multiples.
 */
class RouteAttributeTaggedHandler
{
    #[RouteAttribute(
        name: 'Tagged operation',
        description: 'Operation with multiple tags',
        tags: ['Users', 'Admin']
    )]
    public static function handle(Request $request, array $matches): HTTPResponse
    {
        return new HTTPResponse(200);
    }
}

/**
 * Handler avec RouteAttribute deprecated.
 */
class RouteAttributeDeprecatedHandler
{
    #[RouteAttribute(
        name: 'Deprecated operation',
        description: 'This operation is deprecated',
        deprecated: true
    )]
    public static function handle(Request $request, array $matches): HTTPResponse
    {
        return new HTTPResponse(200);
    }
}

/**
 * Handler avec RouteAttribute et operationId personnalisé.
 */
class RouteAttributeCustomOperationIdHandler
{
    #[RouteAttribute(
        name: 'Custom operation',
        operationId: 'myCustomOperationId'
    )]
    public static function handle(Request $request, array $matches): HTTPResponse
    {
        return new HTTPResponse(200);
    }
}

/**
 * Handler avec RouteAttribute et ContentType HTML.
 */
class RouteAttributeHtmlHandler
{
    #[RouteAttribute(
        name: 'HTML page',
        description: 'Returns an HTML page',
        contentType: ContentType::HTML
    )]
    public static function handle(Request $request, array $matches): HTTPResponse
    {
        return new HTTPResponse(200, '<html></html>');
    }
}

/**
 * Handler avec RouteAttribute ET PHPDoc - l'attribut doit avoir priorité.
 */
class RouteAttributeWithPhpDocHandler
{
    /**
     * From PHPDoc summary.
     *
     * PHPDoc description that should be ignored.
     */
    #[RouteAttribute(
        name: 'From Attribute',
        description: 'Attribute description'
    )]
    public static function handle(Request $request, array $matches): HTTPResponse
    {
        return new HTTPResponse(200);
    }
}

/**
 * Handler avec RouteAttribute sur la classe uniquement.
 */
#[RouteAttribute(tags: ['Users'])]
class RouteAttributeClassTaggedHandler
{
    public static function handle(Request $request, array $matches): HTTPResponse
    {
        return new HTTPResponse(200);
    }
}

/**
 * Handler avec RouteAttribute sur la classe ET la méthode - test de fusion.
 */
#[RouteAttribute(
    name: 'Class name',
    description: 'Class description',
    tags: ['Users']
)]
class RouteAttributeClassAndMethodHandler
{
    #[RouteAttribute(
        name: 'Method name',
        description: 'Method description',
        tags: ['Admin']
    )]
    public static function handle(Request $request, array $matches): HTTPResponse
    {
        return new HTTPResponse(200);
    }
}

/**
 * Handler avec RouteAttribute uniquement sur la classe (pas de méthode).
 */
#[RouteAttribute(
    name: 'Class name',
    description: 'Class description',
    tags: ['Users']
)]
class RouteAttributeClassOnlyHandler
{
    public static function handle(Request $request, array $matches): HTTPResponse
    {
        return new HTTPResponse(200);
    }
}

// === Classes Mappable pour les tests d'intégration Phase 2 ===

use Tivins\Webapp\Mappable;
use DateTime;

/**
 * Un utilisateur pour les tests.
 *
 * @property int $id L'identifiant unique
 * @property string $name Le nom de l'utilisateur
 * @property string $email L'adresse email
 */
class TestUserMappable extends Mappable
{
    public int $id;
    public string $name;
    public string $email;
}

/**
 * Un article pour les tests (avec relation).
 *
 * @property int $id L'identifiant de l'article
 * @property string $title Le titre de l'article
 */
class TestArticleMappable extends Mappable
{
    public int $id;
    public string $title;
    public DateTime $created_at;
}

/**
 * Handler avec returnType simple (classe Mappable).
 */
class RouteAttributeWithReturnTypeHandler
{
    #[RouteAttribute(
        name: 'Get user',
        description: 'Retrieves a user by ID',
        returnType: TestUserMappable::class
    )]
    public static function handle(Request $request, array $matches): HTTPResponse
    {
        return new HTTPResponse(200);
    }
}

/**
 * Handler avec returnType tableau de Mappable.
 */
class RouteAttributeWithArrayReturnTypeHandler
{
    #[RouteAttribute(
        name: 'List users',
        description: 'Retrieves all users',
        returnType: TestUserMappable::class . '[]'
    )]
    public static function handle(Request $request, array $matches): HTTPResponse
    {
        return new HTTPResponse(200);
    }
}

/**
 * Handler avec returnType mapping code HTTP => type.
 */
class RouteAttributeWithMappedReturnTypeHandler
{
    #[RouteAttribute(
        name: 'Get article',
        description: 'Retrieves an article by ID',
        returnType: ['200' => TestArticleMappable::class, '404' => 'object']
    )]
    public static function handle(Request $request, array $matches): HTTPResponse
    {
        return new HTTPResponse(200);
    }
}
