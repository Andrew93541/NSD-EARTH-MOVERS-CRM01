<?php

namespace App\Services\Security;

class ScanResult
{
    public function __construct(
        public readonly bool $isClean,
        public readonly string $status, // 'clean', 'infected', 'unscanned', 'error'
        public readonly ?string $virusName = null,
        public readonly ?string $message = null
    ) {}

    public static function clean(string $message = 'Scanned clean'): self
    {
        return new self(true, 'clean', null, $message);
    }

    public static function infected(string $virusName): self
    {
        return new self(false, 'infected', $virusName, "Malware signature detected: {$virusName}");
    }

    public static function unscanned(string $reason = 'Malware scanner is not configured or in pass-through mode', bool $isClean = true): self
    {
        return new self($isClean, 'unscanned', null, $reason);
    }

    public static function error(string $errorMessage): self
    {
        return new self(false, 'error', null, "Scanner error: {$errorMessage}");
    }

    public static function quarantined(string $reason = 'File quarantined due to security check'): self
    {
        return new self(false, 'quarantined', null, "File quarantined: {$reason}");
    }
}

