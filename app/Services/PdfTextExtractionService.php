<?php

namespace App\Services;

use App\Models\Client;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;

class PdfTextExtractionService
{
    /**
     * Extract text and metadata candidates from a PDF file.
     *
     * @param string $filePath Full filesystem path to the PDF file
     * @param int|null $companyId Company ID for tenant-aware client matching
     * @return array
     */
    public function extract(string $filePath, ?int $companyId = null): array
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return [
                'status' => 'failed',
                'is_scanned' => false,
                'ocr_available' => false,
                'raw_text' => '',
                'message' => 'PDF file not found or unreadable on server.',
                'candidates' => [],
            ];
        }

        $rawContent = file_get_contents($filePath);
        if ($rawContent === false || strlen($rawContent) === 0) {
            return [
                'status' => 'failed',
                'is_scanned' => false,
                'ocr_available' => false,
                'raw_text' => '',
                'message' => 'Uploaded PDF file is empty.',
                'candidates' => [],
            ];
        }

        // 1. Extract digital text from stream objects
        $extractedText = $this->extractTextFromPdfStreams($rawContent);

        // 2. Check if document is scanned or textless
        $cleanAlphaText = preg_replace('/[^A-Za-z0-9]/', '', $extractedText);
        $isScanned = strlen($cleanAlphaText) < 25;

        // 3. Evaluate OCR capability on the host system
        $ocrBinary = $this->detectOcrBinary();
        $ocrAvailable = $ocrBinary !== null;

        if ($isScanned) {
            if ($ocrAvailable) {
                // OCR engine is available (e.g. tesseract or pdftotext)
                $ocrText = $this->runOcrExtraction($filePath, $ocrBinary);
                if (strlen(preg_replace('/[^A-Za-z0-9]/', '', $ocrText)) >= 25) {
                    $extractedText = $ocrText;
                    $isScanned = false; // Successfully recovered via OCR
                }
            }

            if ($isScanned) {
                return [
                    'status' => 'scanned_manual_entry_required',
                    'is_scanned' => true,
                    'ocr_available' => $ocrAvailable,
                    'raw_text' => $extractedText,
                    'message' => 'This document is a scanned image or lacks selectable digital text. ' .
                        ($ocrAvailable ? 'OCR extraction did not yield sufficient text. ' : 'OCR engine is not installed on this server. ') .
                        'Please review the PDF in the viewer and enter details manually.',
                    'candidates' => [
                        'external_reference_number' => null,
                        'title' => null,
                        'client_id' => null,
                        'client_name' => null,
                        'issue_date' => null,
                        'target_completion_date' => null,
                        'total_amount' => null,
                    ],
                ];
            }
        }

        // 4. Parse heuristic fields from digital text
        $candidates = $this->parseFieldCandidates($extractedText, $companyId);

        return [
            'status' => 'extracted',
            'is_scanned' => false,
            'ocr_available' => $ocrAvailable,
            'raw_text' => Str::limit($extractedText, 3000),
            'message' => 'Digital text extracted successfully. Review and confirm fields before saving.',
            'candidates' => $candidates,
        ];
    }

    /**
     * Extract text tokens from PDF streams using native FlateDecode decompression.
     */
    protected function extractTextFromPdfStreams(string $pdfData): string
    {
        $allText = [];

        // Match all stream ... endstream blocks
        if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $pdfData, $matches)) {
            foreach ($matches[1] as $stream) {
                // Attempt decompression (FlateDecode)
                $decompressed = @gzuncompress($stream);
                if ($decompressed === false) {
                    $decompressed = @zlib_decode($stream);
                }

                $targetData = $decompressed !== false ? $decompressed : $stream;
                $textFromStream = $this->parsePdfTextOperators($targetData);
                if (! empty($textFromStream)) {
                    $allText[] = $textFromStream;
                }
            }
        }

        // Also check for uncompressed BT ... ET blocks outside streams
        if (preg_match_all('/BT\s+(.*?)\s+ET/s', $pdfData, $btMatches)) {
            foreach ($btMatches[1] as $block) {
                $textFromBlock = $this->parsePdfTextOperators($block);
                if (! empty($textFromBlock)) {
                    $allText[] = $textFromBlock;
                }
            }
        }

        return trim(implode("\n", $allText));
    }

    /**
     * Parse Tj and TJ text operators inside a text block.
     */
    protected function parsePdfTextOperators(string $content): string
    {
        $textParts = [];

        // 1. Tj operator: (Hello World) Tj
        if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)\s*(?:Tj|\'|")/s', $content, $tjMatches)) {
            foreach ($tjMatches[1] as $rawString) {
                $decoded = $this->decodePdfString($rawString);
                if (trim($decoded) !== '') {
                    $textParts[] = $decoded;
                }
            }
        }

        // 2. TJ operator: [(Hello) 20 (World)] TJ
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $content, $tjArrayMatches)) {
            foreach ($tjArrayMatches[1] as $arrayContent) {
                if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/s', $arrayContent, $stringMatches)) {
                    $combinedLine = '';
                    foreach ($stringMatches[1] as $str) {
                        $combinedLine .= $this->decodePdfString($str);
                    }
                    if (trim($combinedLine) !== '') {
                        $textParts[] = $combinedLine;
                    }
                }
            }
        }

        return implode(' ', $textParts);
    }

    /**
     * Decode standard PDF escape sequences and octal codes.
     */
    protected function decodePdfString(string $input): string
    {
        // Replace octal characters: \ddd
        $decoded = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($m) {
            return chr(octdec($m[1]));
        }, $input);

        // Replace common PDF escapes
        $replacements = [
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\b' => "\x08",
            '\\f' => "\x0C",
            '\\(' => '(',
            '\\)' => ')',
            '\\\\' => '\\',
        ];

        return strtr($decoded, $replacements);
    }

    /**
     * Extract candidate business fields using regex heuristics.
     */
    protected function parseFieldCandidates(string $text, ?int $companyId): array
    {
        $candidates = [
            'external_reference_number' => null,
            'title' => null,
            'client_id' => null,
            'client_name' => null,
            'issue_date' => null,
            'target_completion_date' => null,
            'total_amount' => null,
        ];

        // A. External Reference / Work Order Number
        if (preg_match('/(?:Work\s*Order\s*(?:No|Number|\#)?|W\.?O\.?\s*(?:No|Number|\#)?|P\.?O\.?\s*(?:No|Number|\#)?|Purchase\s*Order\s*(?:No|Number|\#)?|Ref(?:\.|\s*No)?)\s*[:#.\-]?\s*([A-Za-z0-9\/\-_.]{3,35})/i', $text, $matches)) {
            $candidates['external_reference_number'] = trim($matches[1], ' .-');
        }

        // B. Title / Subject / Name of Work
        if (preg_match('/(?:Name\s*of\s*Work|Work\s*Description|Subject|Title|Project\s*Name)\s*[:#.\-]?\s*([^\r\n]{5,120})/i', $text, $matches)) {
            $candidates['title'] = trim($matches[1]);
        }

        // C. Dates
        // Look for Issue Date
        if (preg_match('/(?:Issue\s*Date|WO\s*Date|Order\s*Date|Dated|Date)\s*[:#.\-]?\s*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}|\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2}|(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},?\s+\d{4})/i', $text, $matches)) {
            $parsedDate = $this->normalizeDate($matches[1]);
            if ($parsedDate) {
                $candidates['issue_date'] = $parsedDate;
            }
        }

        // Look for Target Completion Date
        if (preg_match('/(?:Completion\s*Date|Target\s*Date|Valid\s*Till|Delivery\s*Date|Validity)\s*[:#.\-]?\s*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}|\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2}|(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},?\s+\d{4})/i', $text, $matches)) {
            $parsedDate = $this->normalizeDate($matches[1]);
            if ($parsedDate) {
                $candidates['target_completion_date'] = $parsedDate;
            }
        }

        // D. Total Amount / Contract Value
        if (preg_match('/(?:Total\s*(?:Amount|Value)?|Contract\s*Value|Grand\s*Total|Net\s*Amount|Order\s*Value)\s*[:#.\-]?\s*(?:₹|INR|Rs\.?)?\s*([\d,]+\.?\d{0,2})/i', $text, $matches)) {
            $cleanAmount = str_replace(',', '', $matches[1]);
            if (is_numeric($cleanAmount) && (float) $cleanAmount > 0) {
                $candidates['total_amount'] = (float) $cleanAmount;
            }
        }

        // E. Client Name Matching from Database
        if ($companyId) {
            $clients = Client::where('company_id', $companyId)->get();
            foreach ($clients as $client) {
                if ($client->name && stripos($text, $client->name) !== false) {
                    $candidates['client_id'] = $client->id;
                    $candidates['client_name'] = $client->name;
                    break;
                }
                if ($client->company_name && stripos($text, $client->company_name) !== false) {
                    $candidates['client_id'] = $client->id;
                    $candidates['client_name'] = $client->company_name;
                    break;
                }
            }
        }

        return $candidates;
    }

    /**
     * Safely parse and normalize human date into Y-m-d format.
     */
    protected function normalizeDate(string $rawDate): ?string
    {
        try {
            // Replace dots with dashes if dd.mm.yyyy
            $clean = str_replace('.', '-', trim($rawDate));
            $carbon = Carbon::parse($clean);
            return $carbon->format('Y-m-d');
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Detect if a recognized OCR CLI tool is available on the host system.
     */
    protected function detectOcrBinary(): ?string
    {
        $binaries = ['pdftotext', 'tesseract'];
        foreach ($binaries as $bin) {
            $path = shell_exec("command -v {$bin} 2>/dev/null");
            if ($path && trim($path) !== '') {
                return trim($path);
            }
        }
        return null;
    }

    /**
     * Execute OCR extraction if an external engine is present.
     */
    protected function runOcrExtraction(string $filePath, string $binaryPath): string
    {
        $escaped = escapeshellarg($filePath);
        if (str_contains($binaryPath, 'pdftotext')) {
            $output = shell_exec("{$binaryPath} -layout {$escaped} - 2>/dev/null");
            return $output ?: '';
        }

        if (str_contains($binaryPath, 'tesseract')) {
            $tmpTxt = tempnam(sys_get_tempdir(), 'ocr_') . '.txt';
            $baseName = substr($tmpTxt, 0, -4);
            shell_exec("{$binaryPath} {$escaped} {$baseName} 2>/dev/null");
            if (file_exists($tmpTxt)) {
                $text = file_get_contents($tmpTxt);
                @unlink($tmpTxt);
                return $text ?: '';
            }
        }

        return '';
    }
}
