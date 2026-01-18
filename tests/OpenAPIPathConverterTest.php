<?php

declare(strict_types=1);

namespace Tivins\WebappTests;

use PHPUnit\Framework\TestCase;
use Tivins\Webapp\OpenAPIPathConverter;

class OpenAPIPathConverterTest extends TestCase
{
    private OpenAPIPathConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new OpenAPIPathConverter();
    }

    public function testConvertSimplePath(): void
    {
        $result = $this->converter->convert('/users');
        self::assertEquals('/users', $result['path']);
        self::assertEmpty($result['parameters']);
    }

    public function testConvertPathWithSingleParameter(): void
    {
        $result = $this->converter->convert('/users/(\d+)');
        self::assertEquals('/users/{id}', $result['path']);
        self::assertCount(1, $result['parameters']);
        self::assertEquals('id', $result['parameters'][0]['name']);
        self::assertEquals('path', $result['parameters'][0]['in']);
        self::assertTrue($result['parameters'][0]['required']);
        self::assertEquals('integer', $result['parameters'][0]['schema']['type']);
    }

    public function testConvertPathWithMultipleParameters(): void
    {
        $result = $this->converter->convert('/posts/(\d+)/comments/(\d+)');
        self::assertEquals('/posts/{id}/comments/{param1}', $result['path']);
        self::assertCount(2, $result['parameters']);
        self::assertEquals('id', $result['parameters'][0]['name']);
        self::assertEquals('param1', $result['parameters'][1]['name']);
        self::assertEquals('integer', $result['parameters'][0]['schema']['type']);
        self::assertEquals('integer', $result['parameters'][1]['schema']['type']);
    }

    public function testConvertPathWithStringParameter(): void
    {
        $result = $this->converter->convert('/users/(\w+)');
        self::assertEquals('/users/{id}', $result['path']);
        self::assertCount(1, $result['parameters']);
        self::assertEquals('string', $result['parameters'][0]['schema']['type']);
    }

    public function testConvertPathWithComplexPattern(): void
    {
        $result = $this->converter->convert('/search/(.+)/results');
        self::assertEquals('/search/{id}/results', $result['path']);
        self::assertCount(1, $result['parameters']);
        self::assertEquals('string', $result['parameters'][0]['schema']['type']);
    }

    public function testConvertPathWithoutLeadingSlash(): void
    {
        $result = $this->converter->convert('users/(\d+)');
        self::assertStringStartsWith('/', $result['path']);
    }
}
