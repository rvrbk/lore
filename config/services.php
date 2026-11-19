<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'job_scout' => [
        // Base URL of Job Scout, the shared bearer token (LORE_API_TOKEN there) and the account email to count matches for.
        'url' => env('JOB_SCOUT_URL'),
        'token' => env('JOB_SCOUT_TOKEN'),
        'email' => env('JOB_SCOUT_EMAIL'),
    ],

    'google_analytics' => [
        // "Name:GA4 property id" pairs, comma separated, and the path to a service account JSON key with Viewer access.
        'properties' => env('GA_PROPERTIES'),
        'credentials' => env('GA_CREDENTIALS'),
    ],

    'deploy' => [
        // Lets the Deploy page queue a git pull + rebuild that the scheduler runs. Only enable on the server.
        'enabled' => env('DEPLOY_ENABLED', false),
    ],

    'telegram' => [
        // Bot token from @BotFather and the id of the chat that should receive reminders.
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'monitor_alerts' => [
        // Slack/Discord/ntfy-style webhook that receives {"text": ..., "content": ...} when an app goes down or recovers.
        'webhook' => env('MONITOR_ALERT_WEBHOOK'),
    ],

];
