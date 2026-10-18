<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Log;

class ConfigurableFileScanner implements FileScannerInterface
{
    /**
     * Scan the target file at given absolute path.
     */
    public function scan(string $absolutePath): ScanResult
    {
        $isProduction = app()->environment('production');
        $enabled = (bool) config('filesystems.scanner.enabled', true);
        $failClosed = (bool) config('filesystems.scanner.fail_closed', $isProduction);
        $allowUnscannedDev = ! $isProduction && (bool) config('filesystems.scanner.allow_unscanned_dev', false);

        if (! $enabled) {
            if ($isProduction || $failClosed || ! $allowUnscannedDev) {
                return ScanResult::error('Malware scanner is mandatory in production/fail-closed mode but is disabled in configuration.');
            }
            return ScanResult::unscanned('Malware scanner is currently disabled via configuration.', false);
        }

        if (! file_exists($absolutePath)) {
            return ScanResult::error("File not found for scanning at path: {$absolutePath}");
        }

        $clamSocket = config('filesystems.scanner.socket', '/var/run/clamav/clamd.ctl');
        $clamHost = config('filesystems.scanner.host', '127.0.0.1');
        $clamPort = (int) config('filesystems.scanner.port', 3310);
        $timeout = (int) config('filesystems.scanner.timeout', 3);

        try {
            // Check unix domain socket first if configured
            $socket = null;
            if (file_exists($clamSocket)) {
                $socket = @fsockopen("unix://{$clamSocket}", -1, $errno, $errstr, $timeout);
            } elseif ($clamHost) {
                $socket = @fsockopen($clamHost, $clamPort, $errno, $errstr, $timeout);
            }

            if (! $socket) {
                Log::notice('ClamAV daemon unreachable for file scan.', [
                    'path' => $absolutePath,
                    'is_production' => $isProduction,
                    'fail_closed' => $failClosed,
                    'allow_unscanned_dev' => $allowUnscannedDev,
                ]);

                if ($isProduction || $failClosed || ! $allowUnscannedDev) {
                    return ScanResult::error('Anti-malware scanner daemon is unreachable and system policy requires fail-closed verification.');
                }

                return ScanResult::unscanned('ClamAV daemon is currently unreachable.', true);
            }

            stream_set_timeout($socket, $timeout);

            // ClamAV SCAN command
            fwrite($socket, "SCAN {$absolutePath}\n");
            $response = trim((string) fgets($socket));
            fclose($socket);

            if (str_ends_with($response, 'OK')) {
                return ScanResult::clean();
            }

            if (str_contains($response, 'FOUND')) {
                preg_match('/: (.+) FOUND/', $response, $matches);
                $virus = $matches[1] ?? 'Threat detected';
                Log::critical('MALWARE DETECTED in file upload!', [
                    'path' => $absolutePath,
                    'virus' => $virus,
                ]);
                return ScanResult::infected($virus);
            }

            if ($isProduction || $failClosed || ! $allowUnscannedDev) {
                return ScanResult::error("Unrecognized scanner response in fail-closed mode: {$response}");
            }

            return ScanResult::clean();
        } catch (\Throwable $e) {
            Log::warning('File scanner exception during execution: ' . $e->getMessage());

            if ($isProduction || $failClosed || ! $allowUnscannedDev) {
                return ScanResult::error('Scanner exception encountered in fail-closed mode: ' . $e->getMessage());
            }

            return ScanResult::unscanned('Scanner exception encountered: ' . $e->getMessage(), true);
        }
    }
}
