<?php

declare(strict_types=1);

namespace Tivins\Webapp;

class Credential
{
    private static string $secret;

    /**
     * Génère un secret aléatoire de 64 caractères hexadécimaux.
     *
     * Utilise OpenSSL pour générer des bytes cryptographiquement sécurisés.
     * Le secret est utilisé pour signer les tokens JWT.
     *
     * @return void
     *
     * @example
     * ```php
     * Credential::generateSecret();
     * $secret = Credential::getSecret();
     * // Retourne une chaîne hexadécimale de 64 caractères
     * ```
     */
    public static function generateSecret(): void
    {
        self::$secret = bin2hex(openssl_random_pseudo_bytes(32));
    }

    /**
     * Charge un secret depuis un fichier ou en génère un nouveau s'il n'existe pas.
     *
     * Si le fichier n'existe pas ou est vide, génère un nouveau secret
     * et le sauvegarde dans le fichier. Crée le répertoire parent si nécessaire.
     *
     * @param string $file Le chemin vers le fichier contenant le secret
     * @return void
     *
     * @example
     * ```php
     * Credential::loadOrCreate('/path/to/.secret');
     * // Charge le secret depuis le fichier, ou en crée un nouveau s'il n'existe pas
     * ```
     */
    public static function loadOrCreate(string $file): void
    {
        if (!self::load($file)) {
            self::generateSecret();
            $dir = dirname($file);
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            file_put_contents($file, self::$secret);
        }
    }

    /**
     * Charge un secret depuis un fichier.
     *
     * @param string $file Le chemin vers le fichier contenant le secret
     * @return bool True si le secret a été chargé avec succès, false sinon
     *
     * @example
     * ```php
     * if (Credential::load('/path/to/.secret')) {
     *     $secret = Credential::getSecret();
     * }
     * ```
     */
    public static function load(string $file): bool
    {
        if (!is_readable($file)) {
            return false;
        }
        $tempSecret = trim(file_get_contents($file));
        if (empty($tempSecret)) {
            return false;
        }
        self::$secret = $tempSecret;
        return true;
    }

    /**
     * Récupère le secret actuel.
     *
     * ⚠️ Le secret doit être chargé ou généré avant d'appeler cette méthode.
     *
     * @return string Le secret utilisé pour signer les tokens JWT
     *
     * @example
     * ```php
     * Credential::loadOrCreate('.secret');
     * $secret = Credential::getSecret();
     * ```
     */
    public static function getSecret(): string
    {
        return self::$secret;
    }
}