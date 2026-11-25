#!/usr/bin/env php
<?php

/**
 * Opens a sealed vault export or backup on your own computer. (Exports can also be opened without PHP or the repository
 * by the single-file browser tool, scripts/vault-decrypt.html.)
 *
 *   php scripts/vault-decrypt.php <file.sealed> [path-to-private-key] > vault.csv
 *
 * An export (lore-vault-*.csv.sealed) decrypts to Bitwarden CSV, a backup (vault-*.sealed) to JSON.
 * The plain text goes to stdout only, so redirect it somewhere safe and delete it when you are done.
 */
if (! function_exists('sodium_crypto_box_seal_open')) {
    fwrite(STDERR, "The PHP sodium extension is missing (Arch: pacman -S php-sodium).\n");
    exit(1);
}

$file = $argv[1] ?? null;
$keyFile = $argv[2] ?? (getenv('HOME') ?: '.').'/.lore-vault.key';
if ($file === null || ! is_file($file) || ! is_file($keyFile)) {
    fwrite(STDERR, "Usage: php scripts/vault-decrypt.php <file.sealed> [private-key-file] > output\n");
    exit(1);
}

$sealed = file_get_contents($file);
$secret = base64_decode(preg_replace('/^loreprv1:/', '', trim(file_get_contents($keyFile))), true);
if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_BOX_SECRETKEYBYTES) {
    fwrite(STDERR, "The private key file is not valid.\n");
    exit(1);
}

if (str_starts_with($sealed, 'LVT2')) {
    // Export: X25519 + HKDF-SHA256 + AES-256-GCM. Layout: magic, ephemeral public key (32), IV (12), ciphertext, tag (16).
    $ephemeralPublic = substr($sealed, 4, 32);
    $key = hash_hkdf('sha256', sodium_crypto_scalarmult($secret, $ephemeralPublic), 32, 'lore-vault-export-v1', $ephemeralPublic);
    $plain = strlen($sealed) < 64 ? false : openssl_decrypt(substr($sealed, 48, -16), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($sealed, 36, 12), substr($sealed, -16));
} elseif (str_starts_with($sealed, 'LVT1')) {
    // Backup: a libsodium sealed box.
    $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($secret, sodium_crypto_box_publickey_from_secretkey($secret));
    $plain = sodium_crypto_box_seal_open(substr($sealed, 4), $keypair);
} else {
    fwrite(STDERR, "This is not a sealed vault file.\n");
    exit(1);
}

if ($plain === false) {
    fwrite(STDERR, "Could not decrypt: wrong private key, or the file is damaged.\n");
    exit(1);
}

fwrite(STDOUT, $plain);
