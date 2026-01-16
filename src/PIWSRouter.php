<?php

namespace Tivins\Webapp;

class PIWSRouter
{
    public static function init(string $cwd): bool
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (file_exists($cwd . $uri) && !is_dir($cwd . $uri)) {
            return false;
        }
        require $cwd . '/index.php';
        return true;
    }
}