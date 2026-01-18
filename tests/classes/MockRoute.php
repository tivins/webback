<?php

namespace Tivins\WebappTests\classes;

use Tivins\Webapp\RouteAttribute;
use Tivins\Webapp\ContentType;
use Tivins\Webapp\HTTPResponse;
use Tivins\Webapp\Request;
use Tivins\Webapp\RouteInterface;

/**
 * Mock route pour les tests
 */
class MockRoute implements RouteInterface
{
    #[RouteAttribute(
        name: 'Test route',
        description: 'Test route used for testing',
        contentType: ContentType::JSON,
        tags: ['Testing'],
    )]
    public function trigger(Request $request, array $matches): HTTPResponse
    {
        return new HTTPResponse(code: 200, body: ['matches' => $matches, 'path' => $request->path]);
    }
}