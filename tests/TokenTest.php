<?php

namespace Tivins\WebappTests;

use Exception;
use PHPUnit\Framework\TestCase;
use Tivins\Webapp\Credential;
use Tivins\Webapp\Token;

class TokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set a test secret for token generation/validation
        Credential::generateSecret();
    }

    public function testGenerateToken(): void
    {
        $userPayload = ['user_id' => 123, 'username' => 'testuser'];
        $token = Token::generate($userPayload, 3600);
        
        // Token should have 3 parts separated by dots
        $parts = explode('.', $token);
        self::assertCount(3, $parts);
        
        // All parts should be non-empty
        self::assertNotEmpty($parts[0]);
        self::assertNotEmpty($parts[1]);
        self::assertNotEmpty($parts[2]);
    }

    public function testDecodeValidToken(): void
    {
        $userPayload = ['user_id' => 123, 'username' => 'testuser'];
        $token = Token::generate($userPayload, 3600);
        
        $decoded = Token::decode($token);
        
        // Should contain expiration
        self::assertArrayHasKey('exp', $decoded);
        self::assertGreaterThan(time(), $decoded['exp']);
        
        // Should contain user payload
        self::assertEquals(123, $decoded['user_id']);
        self::assertEquals('testuser', $decoded['username']);
    }

    public function testDecodeTokenWithCustomDuration(): void
    {
        $userPayload = ['user_id' => 456];
        $duration = 7200; // 2 hours
        $token = Token::generate($userPayload, $duration);
        
        $decoded = Token::decode($token);
        
        // Expiration should be approximately duration seconds from now
        $expectedExp = time() + $duration;
        self::assertGreaterThanOrEqual($expectedExp - 2, $decoded['exp']);
        self::assertLessThanOrEqual($expectedExp + 2, $decoded['exp']);
    }

    public function testDecodeExpiredToken(): void
    {
        $userPayload = ['user_id' => 789];
        // Generate token with negative duration (already expired)
        $token = Token::generate($userPayload, -1);
        
        // Wait a moment to ensure expiration
        sleep(1);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('token expired');
        
        Token::decode($token);
    }

    public function testDecodeInvalidTokenFormat(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('token invalid');
        
        Token::decode('invalid.token');
    }

    public function testDecodeTokenWithWrongSignature(): void
    {
        $userPayload = ['user_id' => 123];
        $token = Token::generate($userPayload, 3600);
        
        // Modify the signature
        $parts = explode('.', $token);
        $parts[2] = 'wrong_signature';
        $invalidToken = implode('.', $parts);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('signature mismatch');
        
        Token::decode($invalidToken);
    }

    public function testTryDecodeValidToken(): void
    {
        $userPayload = ['user_id' => 999, 'role' => 'admin'];
        $token = Token::generate($userPayload, 3600);
        
        $decoded = Token::tryDecode($token);
        
        self::assertNotFalse($decoded);
        self::assertIsArray($decoded);
        self::assertEquals(999, $decoded['user_id']);
        self::assertEquals('admin', $decoded['role']);
    }

    public function testTryDecodeExpiredToken(): void
    {
        $userPayload = ['user_id' => 111];
        $token = Token::generate($userPayload, -1);
        
        // Wait to ensure expiration
        sleep(1);
        
        $result = Token::tryDecode($token);
        
        self::assertFalse($result);
    }

    public function testTryDecodeInvalidToken(): void
    {
        $result = Token::tryDecode('invalid.token.format');
        
        self::assertFalse($result);
    }

    public function testTryDecodeTokenWithWrongSignature(): void
    {
        $userPayload = ['user_id' => 123];
        $token = Token::generate($userPayload, 3600);
        
        // Modify the signature
        $parts = explode('.', $token);
        $parts[2] = 'wrong_signature';
        $invalidToken = implode('.', $parts);
        
        $result = Token::tryDecode($invalidToken);
        
        self::assertFalse($result);
    }

    public function testTokenWithComplexPayload(): void
    {
        $complexPayload = [
            'user_id' => 42,
            'username' => 'john_doe',
            'email' => 'john@example.com',
            'roles' => ['admin', 'user'],
            'metadata' => ['key' => 'value', 'number' => 123]
        ];
        
        $token = Token::generate($complexPayload, 3600);
        $decoded = Token::decode($token);
        
        // Remove 'exp' from decoded to compare with original
        unset($decoded['exp']);
        
        self::assertEquals($complexPayload, $decoded);
    }

    public function testTokenWithEmptyPayload(): void
    {
        $token = Token::generate([], 3600);
        $decoded = Token::decode($token);
        
        // Should only have 'exp' key
        self::assertCount(1, $decoded);
        self::assertArrayHasKey('exp', $decoded);
    }

    public function testTokenRoundTrip(): void
    {
        $originalPayload = ['user_id' => 555, 'name' => 'Test User'];
        $token = Token::generate($originalPayload, 3600);
        
        // Decode and verify
        $decoded = Token::decode($token);
        unset($decoded['exp']); // Remove expiration for comparison
        
        self::assertEquals($originalPayload, $decoded);
        
        // Try decode should also work
        $tryDecoded = Token::tryDecode($token);
        self::assertNotFalse($tryDecoded);
        unset($tryDecoded['exp']);
        self::assertEquals($originalPayload, $tryDecoded);
    }
}
