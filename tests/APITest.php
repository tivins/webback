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
use Tivins\Webapp\RouteInterface;
use Tivins\Webapp\RouteMenu;

/**
 * Mock route pour les tests
 */
class MockRoute implements RouteInterface
{
    public function trigger(Request $request, array $matches): HTTPResponse
    {
        return new HTTPResponse(code: 200, body: ['matches' => $matches, 'path' => $request->path]);
    }
}


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
            '/users' => new RouteMenu(MockRoute::class, HTTPMethod::GET),
            '/users/(\d+)' => new RouteMenu(MockRoute::class, HTTPMethod::GET),
            '/users' => new RouteMenu(MockRoute::class, HTTPMethod::POST),
        ];
        
        $result = $api->setRoutes($routes);
        self::assertSame($api, $result);
    }

    public function testExecuteRouteNotFound(): void
    {
        $api = new API();
        $api->setRoutes([
            '/users' => new RouteMenu(MockRoute::class, HTTPMethod::GET),
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
            '/users' => new RouteMenu(MockRoute::class, HTTPMethod::GET),
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
            '/users' => new RouteMenu(MockRoute::class, HTTPMethod::GET),
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
            '/users/(\d+)' => new RouteMenu(MockRoute::class, HTTPMethod::GET),
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
            '/users' => new RouteMenu(MockRoute::class, HTTPMethod::GET),
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
            '/items' => new RouteMenu(MockRoute::class, HTTPMethod::GET),
        ]);
        $api->setRoutes([
            '/items' => new RouteMenu(MockRoute::class, HTTPMethod::POST),
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
            '/users/.*' => new RouteMenu(MockRoute::class, HTTPMethod::GET),
            '/users/special' => new RouteMenu(MockRoute::class, HTTPMethod::GET),
        ]);

        // La première route qui matche devrait être utilisée
        $request = new Request(method: HTTPMethod::GET, path: '/users/special');
        $response = $api->execute($request);
        self::assertEquals(200, $response->code);
    }

}
