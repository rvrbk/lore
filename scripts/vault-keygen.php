#!/usr/bin/env php
<?php

/**
 * Makes the keypair that exports and backups are sealed to. Run it on YOUR computer, never on the server.
 *
 *   php scripts/vault-keygen.php [path-for-private-key]
 *
 * The private key is written to a file (default ~/.lore-vault.key, mode 0600): keep it in your password manager or on an
 * offline drive, because without it the backups cannot be opened. Copy the printed public key into the server's .env as
 * VAULT_PUBLIC_KEY. Needs the PHP sodium extension.
 */
if (! function_exists('sodium_crypto_box_keypair')) {
    fwrite(STDERR, "The PHP sodium extension is missing (Arch: pacman -S php-sodium).\n");
    exit(1);
}

$path = $argv[1] ?? (getenv('HOME') ?: '.').'/.lore-vault.key';
if (file_exists($path)) {
    fwrite(STDERR, "$path already exists. Refusing to overwrite a private key.\n");
    exit(1);
}

$keypair = sodium_crypto_box_keypair();
$secret = sodium_crypto_box_secretkey($keypair);
$public = sodium_crypto_box_publickey($keypair);

touch($path);
chmod($path, 0600);
file_put_contents($path, base64_encode($secret)."\n");
sodium_memzero($secret);

echo "Private key written to $path (keep it off the server, and back it up).\n\n";
echo "Add this line to the server's .env:\n\n";
echo 'VAULT_PUBLIC_KEY='.base64_encode($public)."\n";
