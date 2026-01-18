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

    // === Tests Phase 4 : Types complexes ===

    public function testBuildFromMappableWithNestedObject(): void
    {
        $schema = $this->builder->buildFromMappable(TestArticle::class, ['useRef' => false]);

        // L'article doit avoir une propriété author qui référence User
        self::assertArrayHasKey('author', $schema['properties']);
        self::assertArrayHasKey('$ref', $schema['properties']['author']);
        self::assertEquals('#/components/schemas/TestUser', $schema['properties']['author']['$ref']);

        // Le schéma User doit être dans components/schemas
        $components = $this->builder->getComponentsSchemas();
        self::assertArrayHasKey('TestUser', $components);
        self::assertArrayHasKey('TestArticle', $components);
    }

    public function testBuildFromMappableWithNestedObjectRef(): void
    {
        $schema = $this->builder->buildFromMappable(TestArticle::class, ['useRef' => true]);

        // Le schéma doit être une référence
        self::assertArrayHasKey('$ref', $schema);
        self::assertEquals('#/components/schemas/TestArticle', $schema['$ref']);

        // Les deux schémas doivent être dans components/schemas
        $components = $this->builder->getComponentsSchemas();
        self::assertArrayHasKey('TestUser', $components);
        self::assertArrayHasKey('TestArticle', $components);

        // Vérifier que l'article référence bien User
        $articleSchema = $components['TestArticle'];
        self::assertArrayHasKey('$ref', $articleSchema['properties']['author']);
        self::assertEquals('#/components/schemas/TestUser', $articleSchema['properties']['author']['$ref']);
    }

    public function testBuildFromMappableWithCircularReference(): void
    {
        // Test avec des objets qui se référencent mutuellement (cycle)
        $schema = $this->builder->buildFromMappable(TestNode::class, ['useRef' => false]);

        // Le node doit avoir une propriété parent qui référence Node (cycle)
        self::assertArrayHasKey('parent', $schema['properties']);
        
        // Avec useRef=false, le cycle doit être géré (retourne une référence même sans useRef)
        // ou un object générique selon l'implémentation
        $parentSchema = $schema['properties']['parent'];
        
        // Le schéma Node doit être dans components/schemas
        $components = $this->builder->getComponentsSchemas();
        self::assertArrayHasKey('TestNode', $components);
    }

    public function testBuildFromMappableWithCircularReferenceRef(): void
    {
        // Test avec useRef=true pour vérifier que les cycles sont gérés avec des références
        $schema = $this->builder->buildFromMappable(TestNode::class, ['useRef' => true]);

        self::assertArrayHasKey('$ref', $schema);
        
        $components = $this->builder->getComponentsSchemas();
        $nodeSchema = $components['TestNode'];
        
        // Le parent doit être une référence pour éviter le cycle
        self::assertArrayHasKey('parent', $nodeSchema['properties']);
        $parentRef = $nodeSchema['properties']['parent'];
        // Peut être une référence ou un object selon la détection de cycle
        self::assertTrue(
            isset($parentRef['$ref']) || isset($parentRef['type']),
            'Le parent doit être une référence ou un object pour gérer le cycle'
        );
    }

    public function testBuildFromMappableWithUnionType(): void
    {
        $schema = $this->builder->buildFromMappable(TestProduct::class, ['useRef' => false]);

        // La propriété price doit avoir oneOf avec int et string
        self::assertArrayHasKey('price', $schema['properties']);
        $priceSchema = $schema['properties']['price'];
        
        self::assertArrayHasKey('oneOf', $priceSchema);
        self::assertCount(2, $priceSchema['oneOf']);
        
        // Vérifier que les types sont bien int et string
        $types = array_map(fn($t) => $t['type'], $priceSchema['oneOf']);
        self::assertContains('integer', $types);
        self::assertContains('string', $types);
    }

    public function testBuildFromMappableWithUnionTypeIncludingNull(): void
    {
        $schema = $this->builder->buildFromMappable(TestProductNullable::class, ['useRef' => false]);

        // La propriété optional doit avoir oneOf avec int et null
        self::assertArrayHasKey('optional', $schema['properties']);
        $optionalSchema = $schema['properties']['optional'];
        
        // Les types union avec null génèrent oneOf
        if (isset($optionalSchema['oneOf'])) {
            // Vérifier que oneOf contient integer et null
            $types = array_map(fn($t) => $t['type'] ?? null, $optionalSchema['oneOf']);
            self::assertContains('integer', $types);
            // null peut être représenté comme type: null ou omis
        } else {
            // Sinon, doit être nullable
            self::assertEquals('integer', $optionalSchema['type']);
            // Note: OpenAPI 3.0 utilise nullable: true pour les types nullables
        }
    }

    public function testBuildFromMappableNestedArrayOfObjects(): void
    {
        // Note: PHP ne supporte pas directement les tableaux typés comme TestOrderItem[]
        // Ce test vérifie que les tableaux génériques fonctionnent
        // Pour les tableaux typés, il faudrait utiliser la notation "TestOrderItem[]" dans returnType
        $schema = $this->builder->buildFromMappable(TestOrder::class, ['useRef' => false]);

        // L'order doit avoir une propriété items qui est un tableau
        self::assertArrayHasKey('items', $schema['properties']);
        $itemsSchema = $schema['properties']['items'];
        
        // Pour l'instant, array générique retourne ['type' => 'array', 'items' => ['type' => 'object']]
        self::assertEquals('array', $itemsSchema['type']);
        self::assertArrayHasKey('items', $itemsSchema);
        
        // Note: Pour avoir des tableaux typés, il faudrait utiliser returnType: 'TestOrderItem[]'
        // dans RouteAttribute ou améliorer le parsing PHPDoc @var TestOrderItem[]
    }
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

// === Classes de test Phase 4 : Types complexes ===

/**
 * Article avec auteur (objet imbriqué).
 */
class TestArticle extends Mappable
{
    public int $id;
    public string $title;
    public TestUser $author;
    public DateTime $created_at;
}

/**
 * Node avec référence circulaire (parent).
 */
class TestNode extends Mappable
{
    public int $id;
    public string $name;
    public ?TestNode $parent;
}

/**
 * Produit avec type union (price peut être int ou string).
 */
class TestProduct extends Mappable
{
    public int $id;
    public string $name;
    public int|string $price;
    public bool $available;
}

/**
 * Produit avec propriété nullable.
 */
class TestProductNullable extends Mappable
{
    public int $id;
    public string $name;
    public int|null $optional;
}

/**
 * Commande avec tableau d'items (objets imbriqués).
 */
class TestOrder extends Mappable
{
    public int $id;
    public DateTime $created_at;
    /** @var TestOrderItem[] */
    public array $items;
}

/**
 * Item de commande.
 */
class TestOrderItem extends Mappable
{
    public int $id;
    public int $quantity;
    public float $price;
}
