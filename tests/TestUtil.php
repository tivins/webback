<?php

namespace Tivins\WebappTests;

class TestUtil
{
    public static function getTempFilename($extension = '.txt'): string
    {
        return sys_get_temp_dir() . '/test_' . uniqid(true) . time() . $extension;
    }
}