<?php

declare(strict_types=1);

namespace Tivins\Webapp;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class RouteAttribute
{
    public function __construct(
        public string $name = '',
        public string $description = '',
        public ContentType $contentType = ContentType::JSON,
    ) { }
}