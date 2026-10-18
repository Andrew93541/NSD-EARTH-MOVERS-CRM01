<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Primary Backup Storage Path & Permissions
    |--------------------------------------------------------------------------
    |
    | Durable backups are stored outside the web root in private storage.
    | Default path is storage_path('app/backups') with restricted 0700 permissions.
    |
    */
    'path' => env('BACKUP_STORAGE_PATH', storage_path('app/backups')),

    /*
    |--------------------------------------------------------------------------
    | Backup Encryption at Rest (AEAD Authenticated Encryption)
    |--------------------------------------------------------------------------
    |
    | High-security AEAD authenticated encryption using OpenSSL AES-256-GCM.
    | Tampered ciphertext, modified IVs, or altered authentication tags are
    | cryptographically rejected. Backward compatibility is preserved for legacy
    | AES-256-CBC archives with explicit deprecation notices.
    |
    */
    'encryption' => [
        'enabled' => (bool) env('BACKUP_ENCRYPT', false),
        'key' => env('BACKUP_ENCRYPTION_KEY'),
        'cipher' => env('BACKUP_ENCRYPTION_CIPHER', 'AES-256-GCM'),
        'format_version' => 2,
        'allow_legacy_cbc_decrypt' => (bool) env('BACKUP_ALLOW_LEGACY_CBC_DECRYPT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Recovery Point Objective (RPO) SLA Policies
    |--------------------------------------------------------------------------
    |
    | The business RPO for BuildFlow is 60 minutes (1 Hour) under an hourly
    | backup creation schedule. A 15-minute grace window is provided for backup
    | execution time and scheduler jitter.
    |
    | - Target RPO: 60 minutes
    | - Critical Breach Threshold: 75 minutes (60m + 15m grace)
    |
    */
    'rpo' => [
        'target_minutes' => (int) env('BACKUP_RPO_TARGET_MINUTES', 60),
        'grace_minutes' => (int) env('BACKUP_RPO_GRACE_MINUTES', 15),
        'critical_threshold_minutes' => (int) env('BACKUP_RPO_CRITICAL_MINUTES', 75),
    ],

    /*
    |--------------------------------------------------------------------------
    | Secondary Off-Host Replication Configuration
    |--------------------------------------------------------------------------
    |
    | Architecture for replicating verified durable backups to an off-host
    | secondary destination (isolated directory, mounted volume, or cloud adapter).
    | Prevents host-level single points of failure.
    |
    */
    'replication' => [
        'enabled' => (bool) env('BACKUP_REPLICATION_ENABLED', false),
        'driver' => env('BACKUP_REPLICATION_DRIVER', 'local_secondary'),
        'local_secondary' => [
            'path' => env('BACKUP_SECONDARY_PATH', storage_path('app/backups_secondary')),
        ],
        'max_retries' => (int) env('BACKUP_REPLICATION_RETRIES', 3),
        'retry_delay_ms' => (int) env('BACKUP_REPLICATION_RETRY_DELAY_MS', 100),
        'retention_count' => (int) env('BACKUP_SECONDARY_RETENTION', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Policy (Tiered Counts & Bounded Protected Copies)
    |--------------------------------------------------------------------------
    |
    | How many backup snapshots to retain per frequency tier.
    | To prevent unbounded growth, only the most recent restore-verified
    | recovery copies are protected (default: 2). The latest verified baseline
    | and latest valid backup are NEVER deleted.
    |
    */
    'retention' => [
        'hourly' => (int) env('BACKUP_RETAIN_HOURLY', 24),
        'daily' => (int) env('BACKUP_RETAIN_DAILY', 30),
        'monthly' => (int) env('BACKUP_RETAIN_MONTHLY', 12),
        'max_protected_restore_copies' => (int) env('BACKUP_MAX_PROTECTED_COPIES', 2),
    ],
];
