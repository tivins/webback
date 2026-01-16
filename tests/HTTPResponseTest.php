<?php

declare(strict_types=1);

namespace Tivins\WebappTests;

use PHPUnit\Framework\TestCase;
use Tivins\Webapp\ContentType;
use Tivins\Webapp\HTTPResponse;

class HTTPResponseTest extends TestCase
{
    public function testOutputJsonDoesNotExitAndSetsResponseCode(): void
    {
        $response = new HTTPResponse(code: 201, body: ['ok' => true], contentType: ContentType::JSON);

        ob_start();
        $response->output(false);
        $output = ob_get_clean();

        self::assertSame('{"ok":true}', $output);
        self::assertSame(201, http_response_code());
    }

    public function testOutputTextDoesNotExit(): void
    {
        $response = new HTTPResponse(code: 200, body: 'hello', contentType: ContentType::TEXT);

        ob_start();
        $response->output(false);
        $output = ob_get_clean();

        self::assertSame('hello', $output);
    }
}
