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

    public function createTable(Database $database = null): void
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
    public static function mock(): MockMapRegistry
    {
        return self::get(MockMapRegistry::class);
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
        Registries::init($this->database);
    }

    /**
     * @throws \Exception
     */
    public function test1(): void
    {
        $registry = AppRegistries::mock();
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

    /**
     * Test que l'association Database-classe fonctionne correctement
     * @throws \Exception
     */
    public function testDatabaseAssociation(): void
    {
        // Créer une deuxième base de données
        $tmp2 = TestUtil::getTempFilename();
        $database2 = new Database(new SQLiteConnector($tmp2));

        // Première utilisation avec la base par défaut
        $registry1 = AppRegistries::mock();
        $registry1->createTable($this->database);
        $obj1 = new MockMap(title: 'test1');
        $registry1->save($obj1);

        // Associer database2 à MockMapRegistry
        $registry2 = AppRegistries::get(MockMapRegistry::class, $database2);
        $registry2->createTable($database2);
        $obj2 = new MockMap(title: 'test2');
        $registry2->save($obj2);

        // Vérifier que les deux registries sont différentes
        self::assertNotSame($registry1, $registry2);

        // Vérifier que les données sont séparées
        self::assertEquals(1, count($registry1->findAll()));
        self::assertEquals(1, count($registry2->findAll()));

        // Maintenant, un appel sans $database doit utiliser database2 (l'association)
        $registry3 = AppRegistries::get(MockMapRegistry::class);
        self::assertSame($registry2, $registry3); // Même instance
        self::assertEquals(1, count($registry3->findAll())); // Même données que registry2

        $database2->close();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->database->close();
    }
}