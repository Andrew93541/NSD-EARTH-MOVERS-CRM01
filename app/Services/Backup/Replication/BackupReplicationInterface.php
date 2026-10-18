<?php

namespace App\Services\Backup\Replication;

interface BackupReplicationInterface
{
    /**
     * Replicate a verified backup snapshot to the secondary destination.
     *
     * @param string $backupId
     * @param array $manifest
     * @return array Result containing status, replicated files, checksums, and timestamps.
     */
    public function replicate(string $backupId, array $manifest): array;

    /**
     * Verify whether a backup has been successfully replicated and checksums match.
     *
     * @param string $backupId
     * @return bool
     */
    public function verifyReplication(string $backupId): bool;

    /**
     * Get overall replication status and latest replicated backup information.
     *
     * @return array
     */
    public function getReplicationStatus(): array;

    /**
     * Prune old secondary backup replicas while preserving required retention.
     *
     * @param int $keepCount
     * @return int Number of pruned secondary replicas.
     */
    public function pruneSecondary(int $keepCount): int;
}
