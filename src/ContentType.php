<?php

declare(strict_types=1);

namespace Tivins\Webapp;

enum ContentType: string
{
    case AUTO = '<auto>';
    case JSON = 'application/json';
    case XML = 'application/xml';
    case CSV = 'text/csv';
    case HTML = 'text/html';
    case TEXT = 'text/plain';
}
