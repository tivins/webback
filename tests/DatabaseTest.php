<?php

namespace Tivins\WebappTests;

use PHPUnit\Framework\TestCase;
use Tivins\Webapp\Database;
use Tivins\Webapp\SQLiteConnector;

class DatabaseTest extends TestCase
{
    public function testSQLiteConnection()
    {
        // check file
        $tmpFilename = TestUtil::getTempFilename();
        self::assertFileDoesNotExist($tmpFilename);

        // connect
        $connector = new SQLiteConnector($tmpFilename);
        $db = new Database($connector);
        self::assertFileExists($tmpFilename);

        // close and cleanup
        $db->close();
        self::assertFileDoesNotExist($tmpFilename);
    }
}