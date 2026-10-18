<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Backup Storage Path & Permissions
    |--------------------------------------------------------------------------
    |
    | Durable backups are stored outside the web root in private storage.
    | Default path is storage_path('app/backups') with restricted directory permissions.
    |
    */
    'path' => env('BACKUP_STORAGE_PATH', storage_path('app/backups')),

    /*
    |--------------------------------------------------------------------------
    | Backup Encryption at Rest
    |--------------------------------------------------------------------------
    |
    | Encrypt database dumps and file archives using OpenSSL AES-256-CBC.
    |
    */
    'encryption' => [
        'enabled' => (bool) env('BACKUP_ENCRYPT', false),
        'key' => env('BACKUP_ENCRYPTION_KEY', env('APP_KEY')),
        'cipher' => 'AES-256-CBC',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Policy (Tiered Counts)
    |--------------------------------------------------------------------------
    |
    | How many backup snapshots to retain per frequency tier.
    | The last validated recovery copy is ALWAYS protected from pruning.
    |
    */
    'retention' => [
        'hourly' => (int) env('BACKUP_RETAIN_HOURLY', 24),
        'daily' => (int) env('BACKUP_RETAIN_DAILY', 30),
        'monthly' => (int) env('BACKUP_RETAIN_MONTHLY', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Recovery Point Objective (RPO) SLA Thresholds
    |--------------------------------------------------------------------------
    |
    | Maximum age in minutes before the operational monitoring flags a stale backup.
    | Default SLA is 120 minutes for hourly snapshot schedules.
    |
    */
    'rpo_stale_minutes' => (int) env('BACKUP_RPO_STALE_MINUTES', 120),
];
