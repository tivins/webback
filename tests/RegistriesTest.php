<?php

namespace Tivins\WebappTests;

use PHPUnit\Framework\TestCase;
use Tivins\Webapp\Database;
use Tivins\Webapp\DatabaseRegistry;
use Tivins\Webapp\Mappable;
use Tivins\Webapp\Registries;
use Tivins\Webapp\SQLiteConnector;

class TestRegistry extends DatabaseRegistry
{
    protected string $class = TestMappable::class;
    protected string $tableName = 'TestMappable';
    protected string $primaryKey = 'id';

    public function createTable(Database $database): void
    {
        $helper = $database->getHelper();
        $database->execute(
            $helper->createTable($this->tableName,
                $helper->getAutoincrement('id'),
                $helper->getText('name'),
            )
        );
    }
}

class TestMappable extends Mappable
{
    public function __construct(
        public int $id = 0,
        public string $name = '',
    )
    {
    }
}

class RegistriesTest extends TestCase
{
    private Database $database1;
    private Database $database2;

    protected function setUp(): void
    {
        parent::setUp();
        $tmp1 = TestUtil::getTempFilename('.db');
        $tmp2 = TestUtil::getTempFilename('.db');
        $this->database1 = new Database(new SQLiteConnector($tmp1));
        $this->database2 = new Database(new SQLiteConnector($tmp2));
        Registries::init($this->database1);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Registries::reset();
        $this->database1->close();
        $this->database2->close();
    }

    public function testRelease(): void
    {
        // Créer une instance
        $registry1 = Registries::get(TestRegistry::class);
        $registry1Id = spl_object_id($registry1);

        // Vérifier que c'est la même instance
        $registry2 = Registries::get(TestRegistry::class);
        self::assertSame($registry1, $registry2);

        // Release et vérifier qu'une nouvelle instance est créée
        Registries::release(TestRegistry::class);
        $registry3 = Registries::get(TestRegistry::class);
        self::assertNotSame($registry1, $registry3);
        self::assertNotEquals($registry1Id, spl_object_id($registry3));

        // Vérifier que l'association database est conservée
        $registry4 = Registries::get(TestRegistry::class);
        self::assertSame($registry3, $registry4);
    }

    public function testReleaseWithMultipleDatabases(): void
    {
        // Créer des instances avec différentes databases
        $registry1 = Registries::get(TestRegistry::class, $this->database1);
        $registry2 = Registries::get(TestRegistry::class, $this->database2);

        self::assertNotSame($registry1, $registry2);

        // Release doit supprimer toutes les instances
        Registries::release(TestRegistry::class);

        // Vérifier que de nouvelles instances sont créées
        $registry3 = Registries::get(TestRegistry::class, $this->database1);
        $registry4 = Registries::get(TestRegistry::class, $this->database2);

        self::assertNotSame($registry1, $registry3);
        self::assertNotSame($registry2, $registry4);
    }

    public function testUnbind(): void
    {
        // Associer database2 à TestRegistry
        $registry1 = Registries::get(TestRegistry::class, $this->database2);
        $registry1Id = spl_object_id($registry1);

        // Vérifier que l'association fonctionne
        $registry2 = Registries::get(TestRegistry::class);
        self::assertSame($registry1, $registry2);

        // Unbind : supprime l'association mais conserve les instances
        Registries::unbind(TestRegistry::class);

        // Maintenant get() doit utiliser la database par défaut (database1)
        $registry3 = Registries::get(TestRegistry::class);
        self::assertNotSame($registry1, $registry3);

        // L'instance avec database2 existe toujours
        $registry4 = Registries::get(TestRegistry::class, $this->database2);
        self::assertSame($registry1, $registry4);
    }

    public function testReset(): void
    {
        // Créer des instances avec différentes configurations
        $registry1 = Registries::get(TestRegistry::class, $this->database1);
        $registry2 = Registries::get(TestRegistry::class, $this->database2);

        // Vérifier que les instances existent
        self::assertSame($registry1, Registries::get(TestRegistry::class, $this->database1));
        self::assertSame($registry2, Registries::get(TestRegistry::class, $this->database2));

        // Reset complet
        Registries::reset();

        // Vérifier que tout est réinitialisé
        // Les instances doivent être nouvelles
        $registry3 = Registries::get(TestRegistry::class, $this->database1);
        self::assertNotSame($registry1, $registry3);

        // L'association doit être supprimée
        // Sans reset, on devrait utiliser database2, mais après reset on utilise database1 par défaut
        Registries::init($this->database1);
        $registry4 = Registries::get(TestRegistry::class);
        self::assertNotSame($registry2, $registry4);
    }

    public function testResetClearsDefaultDatabase(): void
    {
        // Initialiser avec database1
        Registries::init($this->database1);
        $registry1 = Registries::get(TestRegistry::class);

        // Reset
        Registries::reset();

        // Vérifier qu'on ne peut plus utiliser get() sans database
        $this->expectException(\RuntimeException::class);
        Registries::get(TestRegistry::class);
    }

    public function testReleaseUnbindResetCombination(): void
    {
        // Setup : créer une association
        $registry1 = Registries::get(TestRegistry::class, $this->database2);
        $registry1Id = spl_object_id($registry1);

        // Release : supprime l'instance mais conserve l'association
        Registries::release(TestRegistry::class);
        $registry2 = Registries::get(TestRegistry::class);
        self::assertNotSame($registry1, $registry2);
        self::assertSame($registry2, Registries::get(TestRegistry::class)); // Utilise toujours database2

        // Unbind : supprime l'association
        Registries::unbind(TestRegistry::class);
        $registry3 = Registries::get(TestRegistry::class);
        self::assertNotSame($registry2, $registry3); // Utilise maintenant database1 par défaut

        // Reset : tout nettoyer
        Registries::reset();
        $this->expectException(\RuntimeException::class);
        Registries::get(TestRegistry::class);
    }
}
