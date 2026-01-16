<?php

namespace Tivins\WebappTests;

use DateTime;
use Exception;
use PHPUnit\Framework\TestCase;
use Tivins\Webapp\Cond;
use Tivins\Webapp\Conditions;
use Tivins\Webapp\Database;
use Tivins\Webapp\DatabaseRegistry;
use Tivins\Webapp\InvalidFieldException;
use Tivins\Webapp\Mappable;
use Tivins\Webapp\Registries;
use Tivins\Webapp\SQLiteConnector;

class MappableTestMock extends Mappable
{
    public function __construct(
        public int      $id = 0,
        public string   $title = '',
        public bool     $isOpen = false,
        public DateTime $lockUntil = new DateTime(),
        public float    $price = 99.99,
    )
    {
        // modification pour les tests afin d'avoir une correspondance db(seconds) vs DateTime(microseconds).
        $this->lockUntil->setTime(12, 0, 0, 0);
    }

    protected function validateField(string $field, mixed $value): mixed
    {
        if ($field == 'title' && $value == 'invalid') {
            throw new InvalidFieldException($this, $field, "Invalid title");
        }
        return parent::validateField($field, $value);
    }
}

class MappableTestMockRegistry extends DatabaseRegistry
{
    protected string $class = MappableTestMock::class;
    protected string $tableName = 'MappableTestMock';
    protected string $primaryKey = 'id';

    public function createTable(Database $database): void
    {
        $helper = $database->getHelper();
        $database->execute(
            $helper->createTable($this->tableName,
                $helper->getAutoincrement('id'),
                $helper->getText('title'),
                $helper->getBoolean('isOpen'),
                $helper->getDateTime('lockUntil'),
                $helper->getReal('price'),
            )
        );
    }
}

class MappableTestRegistries extends Registries
{
    public static function mock(): MappableTestMockRegistry
    {
        return self::get(MappableTestMockRegistry::class);
    }
}

class MappableTest extends TestCase
{
    private Database $database;

    protected function setUp(): void
    {
        parent::setUp();
        $tmp = TestUtil::getTempFilename();
        $this->database = new Database(new SQLiteConnector($tmp));
        Registries::init($this->database);
    }

    /**
     * @throws Exception
     */
    public function test2(): void
    {
        $registry = MappableTestRegistries::mock();
        $registry->createTable($this->database);

        $obj = new MappableTestMock(title: 'invalid', isOpen: true);
        self::expectException(InvalidFieldException::class);
        $registry->save($obj);
    }

    /**
     * @throws Exception
     */
    public function test1(): void
    {
        $registry = MappableTestRegistries::mock();
        $registry->createTable($this->database);
        self::assertEquals([], $registry->findAll());

        $obj = new MappableTestMock(title: 'test', isOpen: true);
        $registry->save($obj);
        self::assertEquals([$obj], $registry->findAll());
        self::assertEquals($obj, $registry->find(1));
        self::assertEquals([$obj], $registry->findAllBy('title', 'test'));
        self::assertEquals([$obj], $registry->findByConditions(new Conditions(new Cond('title', 'test')), 'AND'));

        // JSON Serialize
        self::assertEquals(json_encode([
            'id' => 1,
            'title' => 'test',
            'isOpen' => true,
            'lockUntil' => $obj->lockUntil->format(DATE_ATOM),
            'price' => 99.99,
        ]), json_encode($obj));


        $registry->deleteById($obj->id);
        self::assertEquals([], $registry->findAll());

        $obj = new MappableTestMock(title: 'test', isOpen: true);
        $registry->save($obj);
        self::assertEquals([$obj], $registry->findAll());

        $registry->deleteByConditions(new Conditions(new Cond('title', 'test')), 'AND');
        self::assertEquals([], $registry->findAll());

    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->database->close();
        MappableTestRegistries::release(MappableTestMockRegistry::class);
    }
}