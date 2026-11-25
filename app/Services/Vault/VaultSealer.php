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

    /** Keys made by the browser tool start with these, so a private key can never be mistaken for a public one. */
    public const PUBLIC_PREFIX = 'lorepub1:';

    public const PRIVATE_PREFIX = 'loreprv1:';

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
            throw new RuntimeException('There is no valid public key to lock the export with.');
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
        $secret = self::secretKeyBytes($base64SecretKey);
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

        $secret = self::secretKeyBytes($base64SecretKey);

        $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($secret, sodium_crypto_box_publickey_from_secretkey($secret));
        $plain = sodium_crypto_box_seal_open(substr($sealed, strlen(self::MAGIC)), $keypair);
        if ($plain === false) {
            throw new RuntimeException('Could not decrypt: wrong private key, or the file is damaged.');
        }

        return $plain;
    }

    /** The 32 raw bytes of a private key given as base64, with or without the loreprv1: prefix. */
    public static function secretKeyBytes(#[\SensitiveParameter] string $key): string
    {
        $key = trim($key);
        $secret = base64_decode(str_starts_with($key, self::PRIVATE_PREFIX) ? substr($key, strlen(self::PRIVATE_PREFIX)) : $key, true);
        if ($secret === false || strlen($secret) !== 32) {
            throw new RuntimeException('The private key is not valid.');
        }

        return $secret;
    }

    /**
     * Whether the text is a usable public key (with or without the lorepub1: prefix), including a trial encryption
     * that catches keys which would make the shared secret useless.
     */
    public static function isValidPublicKey(string $key): bool
    {
        if (! function_exists('sodium_crypto_box_seal')) {
            return false;
        }

        try {
            (new self($key))->sealExport('probe');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** A short code to compare a public key by eye, for example 3FA9 07C2 B1D4 5E60. The browser tool shows the same one. */
    public static function fingerprint(string $publicKey): ?string
    {
        $bytes = (new self($publicKey))->publicKeyBytes();

        return $bytes === null ? null : implode(' ', str_split(strtoupper(substr(hash('sha256', $bytes), 0, 16)), 4));
    }

    private function publicKeyBytes(): ?string
    {
        $key = trim((string) $this->publicKey);
        if (str_starts_with($key, self::PUBLIC_PREFIX)) {
            $key = substr($key, strlen(self::PUBLIC_PREFIX));
        }
        $bytes = $key === '' ? false : base64_decode($key, true);

        return $bytes !== false && strlen($bytes) === 32 ? $bytes : null;
    }
}
