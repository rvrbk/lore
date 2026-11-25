<?php

return [

    /*
    | Minutes without any vault activity after which the account password is asked for again, before anything is revealed.
    */
    'idle_lock_minutes' => (int) env('VAULT_IDLE_LOCK_MINUTES', 10),

    /*
    | Base64 public key (see scripts/vault-keygen.php) that encrypted exports and backups are sealed to. The matching private
    | key never goes on the server, so the server can write these files but not read them.
    */
    'public_key' => env('VAULT_PUBLIC_KEY'),

    'backup' => [
        'path' => storage_path('app/vault-backups'),
        'keep_daily' => 7,
        'keep_weekly' => 4,
    ],

    /*
    | Audit trail of reveals and exports: who, which item, from which address. Never the secret itself.
    */
    'log_channel' => 'vault',

];
