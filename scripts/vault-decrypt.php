#!/usr/bin/env php
<?php

/**
 * Opens a sealed vault export or backup on your own computer.
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
if (! str_starts_with($sealed, 'LVT1')) {
    fwrite(STDERR, "This is not a sealed vault file.\n");
    exit(1);
}

$secret = base64_decode(trim(file_get_contents($keyFile)), true);
if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_BOX_SECRETKEYBYTES) {
    fwrite(STDERR, "The private key file is not valid.\n");
    exit(1);
}

$keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($secret, sodium_crypto_box_publickey_from_secretkey($secret));
$plain = sodium_crypto_box_seal_open(substr($sealed, 4), $keypair);
if ($plain === false) {
    fwrite(STDERR, "Could not decrypt: wrong private key, or the file is damaged.\n");
    exit(1);
}

fwrite(STDOUT, $plain);
