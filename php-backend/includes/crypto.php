<?php
/**
 * AI-NOC — Encryption (libsodium preferred, OpenSSL fallback)
 * File: /includes/crypto.php
 */

declare(strict_types=1);

namespace AiNoc;

class Crypto
{
    private string $key;
    private bool $useSodium;

    public function __construct(string $base64Key)
    {
        $this->key = base64_decode($base64Key);
        if (strlen($this->key) < 32) {
            throw new \RuntimeException('APP_KEY must be at least 32 bytes');
        }
        $this->useSodium = extension_loaded('sodium');
    }

    public static function fromConfig(): self
    {
        global $config;
        return new self($config['APP_KEY']);
    }

    public function encrypt(string $plaintext): string
    {
        if ($this->useSodium) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plaintext, $nonce, substr($this->key, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
            return base64_encode($nonce . $cipher);
        }

        // OpenSSL AES-256-GCM fallback
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', substr($this->key, 0, 32), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(string $encoded): string
    {
        $data = base64_decode($encoded);
        if ($data === false) {
            throw new \RuntimeException('Invalid encrypted data');
        }

        if ($this->useSodium) {
            $nonce = substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, substr($this->key, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
            if ($plain === false) {
                throw new \RuntimeException('Decryption failed');
            }
            return $plain;
        }

        // OpenSSL fallback
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $cipher = substr($data, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', substr($this->key, 0, 32), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Decryption failed');
        }
        return $plain;
    }

    public static function generateKey(): string
    {
        return base64_encode(random_bytes(32));
    }
}
