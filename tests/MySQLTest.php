<?php

namespace Tivins\WebappTests;

use Dotenv\Dotenv;
use Exception;
use PHPUnit\Framework\TestCase;
use PDO;
use Tivins\Webapp\Database;
use Tivins\Webapp\MySQLConnector;
use Tivins\Webapp\MySQLHelper;

class MySQLTest extends TestCase
{
    private string $dbName;
    private Database $database;
    private static bool $envLoaded = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Load .env file once
        if (!self::$envLoaded) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
            self::$envLoaded = true;
        }

        // Generate a unique database name
        $this->dbName = 'test_' . uniqid(true) . '_' . time();
        
        // Connect to MySQL without a database to create the test database
        $pdo = new PDO("mysql:host=localhost;port=3306", 'root', 'super');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE DATABASE `{$this->dbName}`");
        
        // Now connect to the test database
        $this->database = new Database($this->getConnector());
    }

    private function getConnector(): MySQLConnector
    {
        return new MySQLConnector(
            dbName: $this->dbName,
            user: $_ENV['MYSQL_TEST_USER'] ?? 'root',
            pass: $_ENV['MYSQL_TEST_PASSWORD'] ?? '',
            host: $_ENV['MYSQL_TEST_HOST'] ?? 'localhost',
            port: (int)($_ENV['MYSQL_TEST_PORT'] ?? 3306)
        );
    }

    public function testMySQLConnection(): void
    {
        // Verify we can execute a query
        $result = $this->database->execute("SELECT DATABASE() as db");
        $row = $result->fetch();
        self::assertEquals($this->dbName, $row->db);
    }

    public function testMySQLHelper(): void
    {
        $helper = $this->database->getHelper();
        self::assertInstanceOf(MySQLHelper::class, $helper);
        
        // Test helper methods
        $autoincrement = $helper->getAutoincrement('id');
        self::assertStringContainsString('AUTO_INCREMENT', $autoincrement);
        self::assertStringContainsString('PRIMARY KEY', $autoincrement);
        
        $text = $helper->getText('title', 255, false, true);
        self::assertStringContainsString('VARCHAR(255)', $text);
        self::assertStringContainsString('NOT NULL', $text);
        
        $integer = $helper->getInteger('count', false, true, '1');
        self::assertStringContainsString('INT', $integer);
        self::assertStringContainsString('NOT NULL', $integer);
        self::assertStringContainsString('DEFAULT 1', $integer);
        
        $real = $helper->getReal('price', false, true, '0.0');
        self::assertStringContainsString('DOUBLE', $real);
        
        $boolean = $helper->getBoolean('active', true, true);
        self::assertStringContainsString('TINYINT(1)', $boolean);
        self::assertStringContainsString('DEFAULT 1', $boolean);
        
        $blob = $helper->getBlob('data', true);
        self::assertStringContainsString('BLOB', $blob);
        self::assertStringContainsString('NOT NULL', $blob);
        
        $decimal = $helper->getDecimal('amount', 10, 2, true, '0.00');
        self::assertStringContainsString('DECIMAL(10,2)', $decimal);
        
        $datetime = $helper->getDateTime('created_at', true, MySQLHelper::CurrentTimestamp);
        self::assertStringContainsString('DATETIME', $datetime);
        self::assertStringContainsString('NOT NULL', $datetime);
    }

    public function testCreateTableAndOperations(): void
    {
        $helper = $this->database->getHelper();
        
        // Create a test table
        $createTable = $helper->createTable(
            'test_table',
            $helper->getAutoincrement('id'),
            $helper->getText('name', 100, false, true),
            $helper->getInteger('value', false, false, '0')
        );
        
        $this->database->execute($createTable);
        
        // Verify table exists
        $result = $this->database->execute("SHOW TABLES LIKE 'test_table'");
        self::assertEquals(1, $result->rowCount());
        
        // Insert data
        $id = $this->database->insert('test_table', [
            'name' => 'Test Item',
            'value' => 42
        ]);
        self::assertEquals(1, $id);
        
        // Read data
        $result = $this->database->loadBy('test_table', 'id', 1);
        $row = $result->fetch();
        self::assertNotNull($row);
        self::assertEquals('Test Item', $row->name);
        self::assertEquals(42, $row->value);
        
        // Update data
        $this->database->update('test_table', ['value' => 100], 'id', 1);
        $result = $this->database->loadBy('test_table', 'id', 1);
        $row = $result->fetch();
        self::assertEquals(100, $row->value);
        
        // Delete data
        $this->database->delete('test_table', 'id', 1);
        $result = $this->database->loadBy('test_table', 'id', 1);
        self::assertEquals(0, $result->rowCount());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Close the database connection
        $this->database->close();
        
        // Drop the test database
        try {
            $pdo = $this->getConnector()->connect()->getInstance();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("DROP DATABASE IF EXISTS `{$this->dbName}`");
        } catch (Exception) {
            // Ignore errors during cleanup
        }
    }
}
