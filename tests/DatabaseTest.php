<?php

namespace Tivins\WebappTests;

use Exception;
use PHPUnit\Framework\TestCase;
use Tivins\Webapp\Database;
use Tivins\Webapp\SQLiteConnector;
use Tivins\Webapp\SQLiteHelper;

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

    public function testSQLiteHelper(): void
    {
        $tmpFilename = TestUtil::getTempFilename('.db');
        $connector = new SQLiteConnector($tmpFilename);
        $db = new Database($connector);
        $helper = $db->getHelper();

        self::assertInstanceOf(SQLiteHelper::class, $helper);

        // Test getUniqueKey
        $uniqueKey = $helper->getUniqueKey(['title', 'author']);
        self::assertStringContainsString('UNIQUE', $uniqueKey);
        self::assertStringContainsString('title', $uniqueKey);
        self::assertStringContainsString('author', $uniqueKey);
        // SQLite ignore le nom dans CREATE TABLE, donc on ne vérifie pas le nom

        // Test createIndex (SQLite ne supporte pas INDEX dans CREATE TABLE)
        $createIndex = $helper->createIndex('books', ['isbn'], 'idx_isbn');
        self::assertStringContainsString('CREATE INDEX', $createIndex);
        self::assertStringContainsString('idx_isbn', $createIndex);
        self::assertStringContainsString('isbn', $createIndex);
        self::assertStringContainsString('ON books', $createIndex);

        // Test createTable with unique key (sans index, car SQLite ne le supporte pas dans CREATE TABLE)
        $createTable = $helper->createTable(
            'books',
            $helper->getAutoincrement('id'),
            $helper->getText('title', 255, false, true),
            $helper->getText('author', 255, false, true),
            $helper->getText('isbn', 13, false, false),
            $helper->getUniqueKey(['title', 'author'])
        );

        $db->execute($createTable);

        // Créer l'index séparément pour SQLite
        $db->execute($createIndex);

        // Insert first book
        $id1 = $db->insert('books', [
            'title' => 'Test Book',
            'author' => 'Test Author',
            'isbn' => '1234567890123'
        ]);
        self::assertEquals(1, $id1);

        // Try to insert duplicate (title + author) - should fail
        try {
            $db->insert('books', [
                'title' => 'Test Book',
                'author' => 'Test Author',
                'isbn' => '9876543210987'
            ]);
            self::fail('Expected exception for duplicate unique key');
        } catch (Exception $e) {
            // Expected: unique constraint violation
            self::assertStringContainsString('UNIQUE', $e->getMessage());
        }

        // Insert different title/author combination - should succeed
        $id2 = $db->insert('books', [
            'title' => 'Test Book 2',
            'author' => 'Test Author',
            'isbn' => '1111111111111'
        ]);
        self::assertEquals(2, $id2);

        // Cleanup
        $db->close();
        if (file_exists($tmpFilename)) {
            unlink($tmpFilename);
        }
    }

    public function testSQLiteHelperGetIndexThrowsException(): void
    {
        $tmpFilename = TestUtil::getTempFilename('.db');
        $connector = new SQLiteConnector($tmpFilename);
        $db = new Database($connector);
        $helper = $db->getHelper();

        self::assertInstanceOf(SQLiteHelper::class, $helper);

        // Test getIndex - doit lever une exception car SQLite ne supporte pas INDEX dans CREATE TABLE
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('SQLite does not support INDEX in CREATE TABLE');
        $helper->getIndex(['isbn'], 'idx_isbn');

        // Cleanup
        $db->close();
        if (file_exists($tmpFilename)) {
            unlink($tmpFilename);
        }
    }
}