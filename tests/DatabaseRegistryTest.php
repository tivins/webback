<?php

namespace Tivins\WebappTests;

use PHPUnit\Framework\TestCase;
use Tivins\Webapp\Database;
use Tivins\Webapp\DatabaseRegistry;
use Tivins\Webapp\Mappable;
use Tivins\Webapp\Registries;
use Tivins\Webapp\SQLiteConnector;

class MockMap extends Mappable
{
    public function __construct(
        public int $id = 0,
        public string $title = '',
    )
    {
    }
}

class MockMapRegistry extends DatabaseRegistry
{
    protected string $class = MockMap::class;
    protected string $tableName = 'MockMap';
    protected string $primaryKey = 'id';

    public function createTable(Database $database): void
    {
        $helper = $database->getHelper();
        $database->execute(
            $helper->createTable($this->tableName,
                $helper->getAutoincrement('id'),
                $helper->getText('title'),
            )
        );
    }
}

class AppRegistries extends Registries
{
    public static function mock(Database $database): MockMapRegistry
    {
        return self::get(MockMapRegistry::class, $database);
    }
}

class DatabaseRegistryTest extends TestCase
{
    private Database $database;

    protected function setUp(): void
    {
        parent::setUp();
        $tmp = TestUtil::getTempFilename();
        $this->database = new Database(new SQLiteConnector($tmp));
    }

    public function test1(): void
    {
        $registry = AppRegistries::mock($this->database);
        $registry->createTable($this->database);
        self::assertEquals([], $registry->findAll());

        // insert
        $obj = new MockMap(title: 'test');
        $registry->save($obj);
        self::assertEquals([$obj], $registry->findAll());
        self::assertEquals($obj, $registry->find(1));

        // Update
        $obj->title = 'test changed';
        $result = $registry->save($obj);
        self::assertEquals($obj, $registry->find(1));

        // delete
        $registry->delete($obj);
        self::assertEquals(null, $registry->find(1));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->database->close();
    }
}