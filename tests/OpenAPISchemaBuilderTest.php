<?php

declare(strict_types=1);

namespace Tivins\WebappTests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tivins\Webapp\Mappable;
use Tivins\Webapp\OpenAPISchemaBuilder;
use DateTime;

class OpenAPISchemaBuilderTest extends TestCase
{
    private OpenAPISchemaBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new OpenAPISchemaBuilder();
        $this->builder->clearCache();
    }

    // === Tests pour les types primitifs ===

    public function testBuildFromTypeNameInt(): void
    {
        $schema = $this->builder->buildFromTypeName('int');
        self::assertEquals(['type' => 'integer'], $schema);
    }

    public function testBuildFromTypeNameInteger(): void
    {
        $schema = $this->builder->buildFromTypeName('integer');
        self::assertEquals(['type' => 'integer'], $schema);
    }

    public function testBuildFromTypeNameFloat(): void
    {
        $schema = $this->builder->buildFromTypeName('float');
        self::assertEquals(['type' => 'number'], $schema);
    }

    public function testBuildFromTypeNameDouble(): void
    {
        $schema = $this->builder->buildFromTypeName('double');
        self::assertEquals(['type' => 'number'], $schema);
    }

    public function testBuildFromTypeNameBool(): void
    {
        $schema = $this->builder->buildFromTypeName('bool');
        self::assertEquals(['type' => 'boolean'], $schema);
    }

    public function testBuildFromTypeNameBoolean(): void
    {
        $schema = $this->builder->buildFromTypeName('boolean');
        self::assertEquals(['type' => 'boolean'], $schema);
    }

    public function testBuildFromTypeNameString(): void
    {
        $schema = $this->builder->buildFromTypeName('string');
        self::assertEquals(['type' => 'string'], $schema);
    }

    public function testBuildFromTypeNameArray(): void
    {
        $schema = $this->builder->buildFromTypeName('array');
        self::assertEquals(['type' => 'array', 'items' => ['type' => 'object']], $schema);
    }

    public function testBuildFromTypeNameObject(): void
    {
        $schema = $this->builder->buildFromTypeName('object');
        self::assertEquals(['type' => 'object'], $schema);
    }

    public function testBuildFromTypeNameMixed(): void
    {
        $schema = $this->builder->buildFromTypeName('mixed');
        self::assertEquals(['type' => 'object'], $schema);
    }

    // === Tests pour DateTime ===

    public function testBuildFromTypeNameDateTime(): void
    {
        $schema = $this->builder->buildFromTypeName('DateTime');
        self::assertEquals('string', $schema['type']);
        self::assertEquals('date-time', $schema['format']);
        self::assertArrayHasKey('example', $schema);
    }

    public function testBuildFromTypeNameDateTimeWithBackslash(): void
    {
        $schema = $this->builder->buildFromTypeName('\DateTime');
        self::assertEquals('string', $schema['type']);
        self::assertEquals('date-time', $schema['format']);
    }

    public function testBuildFromTypeNameDateTimeImmutable(): void
    {
        $schema = $this->builder->buildFromTypeName('DateTimeImmutable');
        self::assertEquals('string', $schema['type']);
        self::assertEquals('date-time', $schema['format']);
    }

    // === Tests pour les tableaux ===

    public function testBuildFromTypeNameArrayOfStrings(): void
    {
        $schema = $this->builder->buildFromTypeName('string[]');
        self::assertEquals('array', $schema['type']);
        self::assertEquals(['type' => 'string'], $schema['items']);
    }

    public function testBuildFromTypeNameArrayOfIntegers(): void
    {
        $schema = $this->builder->buildFromTypeName('int[]');
        self::assertEquals('array', $schema['type']);
        self::assertEquals(['type' => 'integer'], $schema['items']);
    }

    public function testBuildArraySchema(): void
    {
        $schema = $this->builder->buildArraySchema('string');
        self::assertEquals('array', $schema['type']);
        self::assertEquals(['type' => 'string'], $schema['items']);
    }

    // === Tests pour les types complexes non-Mappable ===

    public function testBuildFromTypeNameUnknownClass(): void
    {
        // Une classe qui n'existe pas ou qui n'est pas Mappable
        $schema = $this->builder->buildFromTypeName('SomeUnknownClass');
        self::assertEquals(['type' => 'object'], $schema);
    }

    // === Tests pour Mappable ===

    public function testBuildFromMappableSimple(): void
    {
        $schema = $this->builder->buildFromMappable(TestUser::class, ['useRef' => false]);

        self::assertEquals('object', $schema['type']);
        self::assertArrayHasKey('properties', $schema);
        self::assertArrayHasKey('id', $schema['properties']);
        self::assertArrayHasKey('name', $schema['properties']);
        self::assertArrayHasKey('email', $schema['properties']);
        self::assertEquals(['type' => 'integer'], $schema['properties']['id']);
        self::assertEquals(['type' => 'string'], $schema['properties']['name']);
        self::assertEquals(['type' => 'string'], $schema['properties']['email']);
    }

    public function testBuildFromMappableWithRef(): void
    {
        $schema = $this->builder->buildFromMappable(TestUser::class, ['useRef' => true]);

        self::assertArrayHasKey('$ref', $schema);
        self::assertEquals('#/components/schemas/TestUser', $schema['$ref']);
    }

    public function testBuildFromMappableRegistersInComponents(): void
    {
        $this->builder->buildFromMappable(TestUser::class, ['useRef' => true]);

        $components = $this->builder->getComponentsSchemas();
        self::assertArrayHasKey('TestUser', $components);
        self::assertEquals('object', $components['TestUser']['type']);
    }

    public function testBuildFromMappableWithDateTime(): void
    {
        $schema = $this->builder->buildFromMappable(TestUserWithDate::class, ['useRef' => false]);

        self::assertArrayHasKey('created_at', $schema['properties']);
        self::assertEquals('string', $schema['properties']['created_at']['type']);
        self::assertEquals('date-time', $schema['properties']['created_at']['format']);
    }

    public function testBuildFromMappableRequiredFields(): void
    {
        $schema = $this->builder->buildFromMappable(TestUserRequired::class, ['useRef' => false]);

        self::assertArrayHasKey('required', $schema);
        self::assertContains('id', $schema['required']);
        self::assertContains('name', $schema['required']);
    }

    public function testBuildFromMappableWithPropertyDescriptions(): void
    {
        $schema = $this->builder->buildFromMappable(TestUserWithDocs::class, [
            'useRef' => false,
        ]);

        // Les descriptions sont toujours incluses maintenant (Phase 3)
        self::assertArrayHasKey('description', $schema);
        self::assertEquals('Un utilisateur de test.', $schema['description']);
        self::assertArrayHasKey('description', $schema['properties']['id']);
        self::assertEquals("L'identifiant unique", $schema['properties']['id']['description']);
    }

    public function testBuildFromMappableWithVarDescriptions(): void
    {
        $schema = $this->builder->buildFromMappable(TestUserWithVar::class, [
            'useRef' => false,
        ]);

        // @var sur les propriétés individuelles a priorité sur @property
        self::assertArrayHasKey('description', $schema['properties']['id']);
        self::assertEquals('Identifiant unique de l\'utilisateur', $schema['properties']['id']['description']);
        self::assertArrayHasKey('description', $schema['properties']['email']);
        self::assertEquals('Adresse email valide', $schema['properties']['email']['description']);
    }

    public function testBuildFromMappableWithMultilinePropertyDescriptions(): void
    {
        $schema = $this->builder->buildFromMappable(TestUserMultilineDocs::class, [
            'useRef' => false,
        ]);

        // Les descriptions multi-lignes doivent être concaténées
        self::assertArrayHasKey('description', $schema['properties']['name']);
        self::assertStringContainsString('nom complet', $schema['properties']['name']['description']);
        self::assertStringContainsString('utilisateur', $schema['properties']['name']['description']);
    }

    public function testBuildFromMappableWithPropertyReadWrite(): void
    {
        $schema = $this->builder->buildFromMappable(TestUserPropertyReadWrite::class, [
            'useRef' => false,
        ]);

        // @property-read et @property-write doivent être parsés
        self::assertArrayHasKey('description', $schema['properties']['readonly']);
        self::assertEquals('Propriété en lecture seule', $schema['properties']['readonly']['description']);
        self::assertArrayHasKey('description', $schema['properties']['writeonly']);
        self::assertEquals('Propriété en écriture seule', $schema['properties']['writeonly']['description']);
    }

    public function testBuildFromMappableDescriptionsAlwaysIncluded(): void
    {
        // Sans includeDescriptions, les descriptions doivent quand même être incluses (Phase 3)
        $schema = $this->builder->buildFromMappable(TestUserWithDocs::class, [
            'useRef' => false,
            // Pas de includeDescriptions
        ]);

        self::assertArrayHasKey('description', $schema);
        self::assertArrayHasKey('description', $schema['properties']['id']);
    }

    public function testBuildFromMappableThrowsOnInvalidClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->builder->buildFromMappable(\stdClass::class);
    }

    public function testBuildFromMappableCache(): void
    {
        // Premier appel
        $schema1 = $this->builder->buildFromMappable(TestUser::class, ['useRef' => false]);
        // Second appel - devrait utiliser le cache
        $schema2 = $this->builder->buildFromMappable(TestUser::class, ['useRef' => false]);

        self::assertEquals($schema1, $schema2);
    }

    public function testBuildFromTypeNameMappableClass(): void
    {
        $schema = $this->builder->buildFromTypeName(TestUser::class);

        self::assertArrayHasKey('$ref', $schema);
        self::assertEquals('#/components/schemas/TestUser', $schema['$ref']);
    }

    public function testBuildFromTypeNameArrayOfMappable(): void
    {
        $schema = $this->builder->buildFromTypeName(TestUser::class . '[]');

        self::assertEquals('array', $schema['type']);
        self::assertArrayHasKey('$ref', $schema['items']);
        self::assertEquals('#/components/schemas/TestUser', $schema['items']['$ref']);
    }

    public function testClearCache(): void
    {
        $this->builder->buildFromMappable(TestUser::class, ['useRef' => true]);
        self::assertNotEmpty($this->builder->getComponentsSchemas());

        $this->builder->clearCache();
        self::assertEmpty($this->builder->getComponentsSchemas());
    }

    // Note: Les tests pour les types union sont prévus pour la Phase 4
    // Ils nécessitent des modifications à Mappable::reflection() qui ne supporte pas encore les types union
}

// === Classes de test ===

/**
 * Classe Mappable simple pour les tests.
 */
class TestUser extends Mappable
{
    public int $id;
    public string $name;
    public string $email;
}

/**
 * Classe Mappable avec DateTime.
 */
class TestUserWithDate extends Mappable
{
    public int $id;
    public string $name;
    public DateTime $created_at;
}

/**
 * Classe Mappable avec propriétés requises (sans valeur par défaut).
 */
class TestUserRequired extends Mappable
{
    public int $id;
    public string $name;
}

/**
 * Un utilisateur de test.
 *
 * @property int $id L'identifiant unique
 * @property string $name Le nom de l'utilisateur
 */
class TestUserWithDocs extends Mappable
{
    public int $id;
    public string $name;
}

/**
 * Utilisateur avec descriptions @var sur les propriétés.
 */
class TestUserWithVar extends Mappable
{
    /** @var int Identifiant unique de l'utilisateur */
    public int $id;
    
    /** @var string Adresse email valide */
    public string $email;
}

/**
 * Utilisateur avec descriptions multi-lignes.
 * 
 * @property string $name Le nom complet
 * de l'utilisateur avec plusieurs lignes
 * pour tester le parsing multi-ligne
 */
class TestUserMultilineDocs extends Mappable
{
    public int $id;
    public string $name;
}

/**
 * Utilisateur avec @property-read et @property-write.
 * 
 * @property-read int $readonly Propriété en lecture seule
 * @property-write string $writeonly Propriété en écriture seule
 */
class TestUserPropertyReadWrite extends Mappable
{
    public int $readonly;
    public string $writeonly;
}
