<?php

namespace App\Services\Vault;

use RuntimeException;

/**
 * Public-key encryption (libsodium sealed boxes) for exports and backups.
 *
 * The server only ever holds the public key, so it can write these files but cannot read them back. The private key
 * stays on the owner's own computer.
 */
class VaultSealer
{
    /** First bytes of every sealed file, so the decrypt script can tell it is looking at the right thing. */
    public const MAGIC = 'LVT1';

    /**
     * Exports use a second format built only from standard parts (X25519, HKDF-SHA256, AES-256-GCM), so they can be opened by
     * the browser tool in scripts/vault-decrypt.html without installing anything. Layout: magic, ephemeral public key (32),
     * IV (12), ciphertext, GCM tag (16).
     */
    public const EXPORT_MAGIC = 'LVT2';

    private const EXPORT_INFO = 'lore-vault-export-v1';

    public function __construct(private readonly ?string $publicKey = null) {}

    public static function fromConfig(): self
    {
        return new self(config('vault.public_key'));
    }

    public function isAvailable(): bool
    {
        return function_exists('sodium_crypto_box_seal') && $this->publicKeyBytes() !== null;
    }

    public function seal(#[\SensitiveParameter] string $plaintext): string
    {
        if (! function_exists('sodium_crypto_box_seal')) {
            throw new RuntimeException('The PHP sodium extension is not installed.');
        }

        $key = $this->publicKeyBytes();
        if ($key === null) {
            throw new RuntimeException('VAULT_PUBLIC_KEY is missing or not a valid public key. Generate one with scripts/vault-keygen.php.');
        }

        return self::MAGIC.sodium_crypto_box_seal($plaintext, $key);
    }

    public function sealExport(#[\SensitiveParameter] string $plaintext): string
    {
        if (! function_exists('sodium_crypto_box_seal')) {
            throw new RuntimeException('The PHP sodium extension is not installed.');
        }

        $recipient = $this->publicKeyBytes();
        if ($recipient === null) {
            throw new RuntimeException('VAULT_PUBLIC_KEY is missing or not a valid public key. Generate one with scripts/vault-keygen.php.');
        }

        $ephemeral = sodium_crypto_box_keypair();
        $ephemeralPublic = sodium_crypto_box_publickey($ephemeral);
        $key = hash_hkdf('sha256', sodium_crypto_scalarmult(sodium_crypto_box_secretkey($ephemeral), $recipient), 32, self::EXPORT_INFO, $ephemeralPublic);
        $iv = random_bytes(12);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        sodium_memzero($key);

        return self::EXPORT_MAGIC.$ephemeralPublic.$iv.$ciphertext.$tag;
    }

    /** Opens an export made by sealExport (the same job scripts/vault-decrypt.html does in a browser). */
    public static function openExport(string $file, #[\SensitiveParameter] string $base64SecretKey): string
    {
        $secret = base64_decode(trim($base64SecretKey), true);
        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_BOX_SECRETKEYBYTES) {
            throw new RuntimeException('The private key is not valid.');
        }
        if (! str_starts_with($file, self::EXPORT_MAGIC) || strlen($file) < 4 + 32 + 12 + 16) {
            throw new RuntimeException('This is not an exported vault file.');
        }

        $ephemeralPublic = substr($file, 4, 32);
        $iv = substr($file, 36, 12);
        $tag = substr($file, -16);
        $ciphertext = substr($file, 48, -16);
        $key = hash_hkdf('sha256', sodium_crypto_scalarmult($secret, $ephemeralPublic), 32, self::EXPORT_INFO, $ephemeralPublic);
        $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        sodium_memzero($key);
        if ($plain === false) {
            throw new RuntimeException('Could not decrypt: wrong private key, or the file is damaged.');
        }

        return $plain;
    }

    /**
     * Opens a sealed file. Needs the private key, so this runs on the owner's computer (scripts/vault-decrypt.php, vault:restore).
     */
    public static function open(string $sealed, #[\SensitiveParameter] string $base64SecretKey): string
    {
        if (! str_starts_with($sealed, self::MAGIC)) {
            throw new RuntimeException('This is not a sealed vault file.');
        }

        $secret = base64_decode(trim($base64SecretKey), true);
        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_BOX_SECRETKEYBYTES) {
            throw new RuntimeException('The private key is not valid.');
        }

        $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($secret, sodium_crypto_box_publickey_from_secretkey($secret));
        $plain = sodium_crypto_box_seal_open(substr($sealed, strlen(self::MAGIC)), $keypair);
        if ($plain === false) {
            throw new RuntimeException('Could not decrypt: wrong private key, or the file is damaged.');
        }

        return $plain;
    }

    private function publicKeyBytes(): ?string
    {
        $bytes = $this->publicKey === null || $this->publicKey === '' ? false : base64_decode($this->publicKey, true);

        return $bytes !== false && strlen($bytes) === 32 ? $bytes : null;
    }
}
