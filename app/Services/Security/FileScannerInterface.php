<?php

namespace App\Services\Security;

interface FileScannerInterface
{
    /**
     * Scan an uploaded file on disk before persistence.
     */
    public function scan(string $absolutePath): ScanResult;
}
