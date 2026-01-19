<?php

namespace Tivins\WebappTests\classes;

use DateTime;
use Tivins\Webapp\HTTPResponse;
use Tivins\Webapp\Message;
use Tivins\Webapp\MessageType;
use Tivins\Webapp\Request;
use Tivins\Webapp\RouteAttribute;

#[RouteAttribute(
    tags: ['Testing', 'Example'],
)]
class FullyDescribedRoute
{
    #[RouteAttribute(
        name: 'getSomeObjectWithAuthorization',
        description: 'Get some object with authorization',
        returnType: [
            200 => ExampleMappable::class,
            401 => null,
            404 => Message::class . '[]',
        ]
    )]
    public static function handlerObject(Request $request, array $matches): HTTPResponse
    {
        if ($request->getTokenData() === null) {
            return new HTTPResponse(code: 401);
        }
        $obj = self::getObjectMock();
        if ($obj === null) {
            return new HTTPResponse(code: 404, body: [new Message("Object not found", MessageType::Error)]);
        }
        return new HTTPResponse(body: $obj);
    }

    private static function getObjectMock(): ?ExampleMappable
    {
        return rand(0, 1) ? new ExampleMappable(1, "test", new DateTime("2022-12-21")) : null;
    }
}