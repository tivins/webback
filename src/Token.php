<?php

namespace Tivins\Webapp;


use Exception;

class Token
{
    /**
     * Génère un token JWT.
     *
     * @param array $userPayload Les données à inclure dans le payload du token
     * @param int $durationSeconds La durée de validité du token en secondes (par défaut: 3600 = 1 heure)
     * @return string Le token JWT encodé
     *
     * @example
     * ```php
     * $token = Token::generate(['user_id' => 123, 'role' => 'admin'], 7200);
     * // Retourne: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE2..."
     * ```
     */
    public static function generate(array $userPayload, int $durationSeconds = 3600): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode(['exp' => time() + $durationSeconds] + $userPayload);
        $header64 = self::base64url_encode($header);
        $payload64 = self::base64url_encode($payload);
        $signature = hash_hmac('sha256', $header64 . '.' . $payload64, Credential::getSecret());
        return $header64 . '.' . $payload64 . '.' . $signature;
    }

    /**
     * Tente de décoder un token JWT sans lever d'exception.
     *
     * @param string $token Le token JWT à décoder
     * @return array|false Le payload décodé ou false si le token est invalide/expiré
     *
     * @example
     * ```php
     * $data = Token::tryDecode($token);
     * if ($data !== false) {
     *     $userId = $data['user_id'];
     * } else {
     *     // Token invalide ou expiré
     * }
     * ```
     */
    public static function tryDecode(string $token): array|false
    {
        try {
            return self::decode($token);
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Décode un token JWT et valide sa signature et expiration.
     *
     * @param string $token Le token JWT à décoder
     * @return array Le payload décodé
     * @throws Exception Si le token est invalide, expiré ou si la signature ne correspond pas
     *
     * @example
     * ```php
     * try {
     *     $data = Token::decode($token);
     *     $userId = $data['user_id'];
     * } catch (Exception $e) {
     *     // Gérer l'erreur: token invalide, expiré, etc.
     * }
     * ```
     */
    public static function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception("token invalid");
        }
        // $header = json_decode(self::base64url_decode($parts[0]), true);
        $payload = json_decode(self::base64url_decode($parts[1]), true);
        if ($payload === null || $payload['exp'] < time()) {
            throw new Exception("token expired");
        }
        $signature = $parts[2];
        if (hash_hmac('sha256', $parts[0] . '.' . $parts[1], Credential::getSecret()) !== $signature) {
            throw new Exception("signature mismatch");
        }
        return $payload;
    }

    private static function base64url_encode($data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64url_decode($data): false|string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}