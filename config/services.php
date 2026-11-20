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

    'nwsfd' => [
        // Shared bearer token the nwsfd app uses to push the news it has harvested to Lore.
        'token' => env('NWSFD_TOKEN'),
    ],

    'news' => [
        // RSS feed whose headlines flash in the corner of the dashboard map.
        'feed' => env('NEWS_FEED_URL', 'https://feeds.bbci.co.uk/news/world/rss.xml'),
        // RSS feed with UFO sighting news, mixed into the same ticker.
        'ufo_feed' => env('NEWS_UFO_FEED_URL', 'https://news.google.com/rss/search?q=UFO+sighting+OR+UAP&hl=en-US&gl=US&ceid=US:en'),
    ],

    'pointy' => [
        // Pointy's scheduling emails go out from this address (the mail transport itself is set with the MAIL_* variables).
        'from' => env('POINTY_FROM_ADDRESS', 'pointy@verbeek.ug'),
        'from_name' => env('POINTY_FROM_NAME', 'Pointy'),
        // Where new and cancelled bookings are announced; leave empty to only email the guest.
        'owner_email' => env('POINTY_OWNER_EMAIL'),
    ],

    'deploy' => [
        // Lets the Deploy page queue a git pull + rebuild that the scheduler runs. Only enable on the server.
        'enabled' => env('DEPLOY_ENABLED', false),
        // Seconds between the attempts of the post-deploy check that the site answers again.
        'smoke_retry_seconds' => env('DEPLOY_SMOKE_RETRY_SECONDS', 5),
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
