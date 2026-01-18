<?php

declare(strict_types=1);

namespace Tivins\WebappTests;

use PHPUnit\Framework\TestCase;
use Tivins\Webapp\API;
use Tivins\Webapp\HTTPMethod;
use Tivins\Webapp\HTTPResponse;
use Tivins\Webapp\Message;
use Tivins\Webapp\MessageType;
use Tivins\Webapp\Request;
use Tivins\Webapp\RouteConfig;
use Tivins\WebappTests\classes\MockRoute;


class APITest extends TestCase
{
    public function testConstructorWithDefaultBasePath(): void
    {
        $api = new API();
        self::assertEquals('', $api->basePath);
    }

    public function testConstructorWithCustomBasePath(): void
    {
        $api = new API('/api/v1');
        self::assertEquals('/api/v1', $api->basePath);
    }

    public function testSetRoutesReturnsSelf(): void
    {
        $api = new API();
        $result = $api->setRoutes([]);
        self::assertSame($api, $result);
    }

    public function testSetRoutesWithMultipleRoutes(): void
    {
        $api = new API();
        $routes = [
            new RouteConfig('/users', MockRoute::class, HTTPMethod::GET),
            new RouteConfig('/users/(\d+)', MockRoute::class, HTTPMethod::GET),
            new RouteConfig('/users', MockRoute::class, HTTPMethod::POST),
        ];

        $result = $api->setRoutes($routes);
        self::assertSame($api, $result);
    }

    public function testSetRoutesWithFluent(): void
    {
        $api = new API();
        $result = $api
            ->get('/users', MockRoute::class)
            ->get('/users/(\d+)', MockRoute::class)
            ->post('/users', MockRoute::class);
        self::assertSame($api, $result);
    }

    public function testExecuteRouteNotFound(): void
    {
        $api = new API();
        $api->setRoutes([
            new RouteConfig('/users', MockRoute::class, HTTPMethod::GET),
        ]);

        $request = new Request(method: HTTPMethod::GET, path: '/unknown');
        $response = $api->execute($request);

        self::assertEquals(404, $response->code);
        self::assertCount(1, $response->messages);
        self::assertInstanceOf(Message::class, $response->messages[0]);
        self::assertEquals(MessageType::Error, $response->messages[0]->type);
        self::assertStringContainsString('Route not found', $response->messages[0]->text);
    }

    public function testExecuteRouteNotFoundWrongMethod(): void
    {
        $api = new API();
        $api->setRoutes([
            new RouteConfig('/users', MockRoute::class, HTTPMethod::GET),
        ]);

        // Route existe pour GET mais pas pour POST
        $request = new Request(method: HTTPMethod::POST, path: '/users');
        $response = $api->execute($request);

        self::assertEquals(404, $response->code);
    }

    public function testExecuteMatchingRoute(): void
    {
        $api = new API();
        $api->setRoutes([
            new RouteConfig('/users', MockRoute::class, HTTPMethod::GET),
        ]);

        $request = new Request(method: HTTPMethod::GET, path: '/users');
        $response = $api->execute($request);

        self::assertEquals(200, $response->code);
        self::assertEquals('/users', $response->body['path']);
    }

    public function testExecuteMatchingRouteWithCaptures(): void
    {
        $api = new API();
        $api->setRoutes([
            new RouteConfig('/users/(\d+)', MockRoute::class, HTTPMethod::GET),
        ]);

        $request = new Request(method: HTTPMethod::GET, path: '/users/123');
        $response = $api->execute($request);

        self::assertEquals(200, $response->code);
        // Le match[0] (la correspondance complète) est supprimé, il reste match[1].
        self::assertArrayHasKey(1, $response->body['matches']);
        self::assertEquals('123', $response->body['matches'][1]);
    }

    public function testExecuteWithBasePath(): void
    {
        $api = new API('/api/v1');
        $api->setRoutes([
            new RouteConfig('/users', MockRoute::class, HTTPMethod::GET),
        ]);

        // Sans le basePath, la route ne devrait pas matcher
        $request = new Request(method: HTTPMethod::GET, path: '/users');
        $response = $api->execute($request);
        self::assertEquals(404, $response->code);

        // Avec le basePath, la route devrait matcher
        $request = new Request(method: HTTPMethod::GET, path: '/api/v1/users');
        $response = $api->execute($request);
        self::assertEquals(200, $response->code);
    }

    public function testExecuteMultipleMethodsSameRoute(): void
    {
        $api = new API();
        $api->setRoutes([
            new RouteConfig('/items', MockRoute::class, HTTPMethod::GET),
        ]);
        $api->setRoutes([
            new RouteConfig('/items', MockRoute::class, HTTPMethod::POST),
        ]);

        // Test GET
        $getRequest = new Request(method: HTTPMethod::GET, path: '/items');
        $getResponse = $api->execute($getRequest);
        self::assertEquals(200, $getResponse->code);

        // Test POST
        $postRequest = new Request(method: HTTPMethod::POST, path: '/items');
        $postResponse = $api->execute($postRequest);
        self::assertEquals(200, $postResponse->code);
    }

    public function testExecuteFirstMatchingRouteWins(): void
    {
        $api = new API();
        $api->setRoutes([
            new RouteConfig('/users/.*', MockRoute::class, HTTPMethod::GET),
            new RouteConfig('/users/special', MockRoute::class, HTTPMethod::GET),
        ]);

        // La première route qui matche devrait être utilisée
        $request = new Request(method: HTTPMethod::GET, path: '/users/special');
        $response = $api->execute($request);
        self::assertEquals(200, $response->code);
    }

    // === Tests pour les callables ===

    public function testExecuteWithClosure(): void
    {
        $api = new API();
        $api->get('/health', fn(Request $req, array $matches) => new HTTPResponse(200, ['status' => 'ok']));

        $request = new Request(method: HTTPMethod::GET, path: '/health');
        $response = $api->execute($request);

        self::assertEquals(200, $response->code);
        self::assertEquals(['status' => 'ok'], $response->body);
    }

    public function testExecuteWithClosureAndCaptures(): void
    {
        $api = new API();
        $api->get('/users/(\d+)', fn(Request $req, array $matches) => new HTTPResponse(200, ['id' => $matches[1]]));

        $request = new Request(method: HTTPMethod::GET, path: '/users/42');
        $response = $api->execute($request);

        self::assertEquals(200, $response->code);
        self::assertEquals('42', $response->body['id']);
    }

    public function testSetRoutesWithClosure(): void
    {
        $api = new API();
        $api->setRoutes([
            new RouteConfig('/ping', fn($req, $m) => new HTTPResponse(200, ['pong' => true]), HTTPMethod::GET),
        ]);

        $request = new Request(method: HTTPMethod::GET, path: '/ping');
        $response = $api->execute($request);

        self::assertEquals(200, $response->code);
        self::assertTrue($response->body['pong']);
    }

    public function testPostWithClosure(): void
    {
        $api = new API();
        $api->post('/echo', fn(Request $req, array $matches) => new HTTPResponse(201, $req->body));

        $request = new Request(method: HTTPMethod::POST, path: '/echo', body: ['message' => 'hello']);
        $response = $api->execute($request);

        self::assertEquals(201, $response->code);
        self::assertEquals(['message' => 'hello'], $response->body);
    }

    public function testMixedClassAndClosureRoutes(): void
    {
        $api = new API();
        $api->get('/users', MockRoute::class);
        $api->get('/health', fn($req, $m) => new HTTPResponse(200, ['status' => 'ok']));

        // Test route avec classe
        $request1 = new Request(method: HTTPMethod::GET, path: '/users');
        $response1 = $api->execute($request1);
        self::assertEquals(200, $response1->code);
        self::assertArrayHasKey('path', $response1->body);

        // Test route avec closure
        $request2 = new Request(method: HTTPMethod::GET, path: '/health');
        $response2 = $api->execute($request2);
        self::assertEquals(200, $response2->code);
        self::assertEquals(['status' => 'ok'], $response2->body);
    }

    public function testExecuteWithCallableArray(): void
    {
        $api = new API();
        $api->get('/test', [CallableHandler::class, 'handle']);

        $request = new Request(method: HTTPMethod::GET, path: '/test');
        $response = $api->execute($request);

        self::assertEquals(200, $response->code);
        self::assertEquals('callable_array', $response->body['type']);
    }
}

/**
 * Classe helper pour tester les callable arrays.
 */
class CallableHandler
{
    public static function handle(Request $request, array $matches): HTTPResponse
    {
        return new HTTPResponse(200, ['type' => 'callable_array', 'path' => $request->path]);
    }
}
