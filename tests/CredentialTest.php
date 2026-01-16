<?php

declare(strict_types=1);

namespace Tivins\WebappTests;

use PHPUnit\Framework\TestCase;
use Tivins\Webapp\Credential;

class CredentialTest extends TestCase
{
    public function testGenerateSecret()
    {
        // On s'assure que le secret fait la bonne taille.
        Credential::generateSecret();
        $secret = Credential::getSecret();
        self::assertEquals(64, strlen($secret));

        // On s'assure que chaque appel fourni un secret différent.
        Credential::generateSecret();
        $newSecret = Credential::getSecret();
        self::assertNotEquals($newSecret, $secret);
    }

    public function testLoad()
    {
        $tmpFilename = tempnam(sys_get_temp_dir(), 'test');

        // On s'assure d'avoir FALSE si le fichier n'existe pas.
        unlink($tmpFilename);
        self::assertFileDoesNotExist($tmpFilename);
        self::assertFalse(Credential::load($tmpFilename));

        // On s'assure d'avoir FALSE si le fichier est vide.
        $bytes = file_put_contents($tmpFilename, '');
        self::assertEquals(0, $bytes);
        self::assertFileExists($tmpFilename);
        self::assertFalse(Credential::load($tmpFilename));

        // Test avec fichier existant
        $secret = 'secret';
        $bytes = file_put_contents($tmpFilename, $secret);
        self::assertEquals($bytes, strlen($secret));
        self::assertFileExists($tmpFilename);
        self::assertTrue(Credential::load($tmpFilename));
        self::assertEquals($secret, Credential::getSecret());

        // cleanup
        unlink($tmpFilename);
    }

    public function testLoadOrCreate()
    {
        $tmpFilename = tempnam(sys_get_temp_dir(), 'test');
        unlink($tmpFilename);
        Credential::loadOrCreate($tmpFilename);
        self::assertFileExists($tmpFilename);
    }
}