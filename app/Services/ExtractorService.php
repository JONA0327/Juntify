<?php

namespace App\Services;

use App\Support\OpenAiConfig;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class ExtractorService
{
    /**
     * Extracts normalized text content from a document located at the given path.
     *
     * @return array{ text: string, metadata: array }
     */
    public function extract(string $filePath, string $mimeType, ?string $filename = null): array
    {
        $extension = strtolower(pathinfo($filename ?? $filePath, PATHINFO_EXTENSION));

        if ($this->isPdf($mimeType, $extension)) {
            return $this->extractPdf($filePath);
        }

        if ($this->isWordDocument($mimeType, $extension)) {
            return $this->extractWord($filePath, $extension);
        }

        if ($this->isSpreadsheet($mimeType, $extension)) {
            return $this->extractSpreadsheet($filePath, $extension);
        }

        if ($this->isImage($mimeType, $extension)) {
            return $this->extractImage($filePath);
        }

        return $this->extractPlainText($filePath);
    }

    private function isPdf(string $mimeType, string $extension): bool
    {
        return str_contains($mimeType, 'pdf') || $extension === 'pdf';
    }

    private function isWordDocument(string $mimeType, string $extension): bool
    {
        return str_contains($mimeType, 'word') || in_array($extension, ['doc', 'docx']);
    }

    private function isSpreadsheet(string $mimeType, string $extension): bool
    {
        return str_contains($mimeType, 'sheet') || str_contains($mimeType, 'excel')
            || in_array($extension, ['xls', 'xlsx', 'csv']);
    }

    private function isImage(string $mimeType, string $extension): bool
    {
        return str_starts_with($mimeType, 'image/')
            || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tif', 'tiff', 'webp']);
    }

    private function extractPlainText(string $filePath): array
    {
        $contents = File::get($filePath);
        $text = $this->normalizeText($contents ?? '');

        return [
            'text' => $text,
            'metadata' => [
                'detected_type' => 'text/plain',
                'original_bytes' => File::size($filePath),
            ],
        ];
    }

    private function extractPdf(string $filePath): array
    {
        // Prefer native PHP libraries if they are installed.
        if (class_exists('Smalot\\PdfParser\\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filePath);
                $text = $this->normalizeText($pdf->getText());

                return [
                    'text' => $text,
                    'metadata' => [
                        'detected_type' => 'application/pdf',
                        'pages' => count($pdf->getPages()),
                        'engine' => 'smalot/pdfparser',
                    ],
                ];
            } catch (\Throwable $exception) {
                // Fall back to CLI strategy below.
            }
        }

        $binary = $this->resolveBinary('pdftotext');
        if ($binary) {
            $outputFile = $filePath . '.txt';
            $process = new Process([$binary, '-layout', $filePath, $outputFile]);
            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('No se pudo extraer texto del PDF: ' . $process->getErrorOutput());
            }

            $text = File::exists($outputFile) ? File::get($outputFile) : '';
            if ($text === false) {
                $text = '';
            }

            if ($outputFile && File::exists($outputFile)) {
                File::delete($outputFile);
            }

            return [
                'text' => $this->normalizeText($text),
                'metadata' => [
                    'detected_type' => 'application/pdf',
                    'engine' => 'pdftotext-cli',
                ],
            ];
        }

        // OCR fallback for PDFs: convert pages to images with pdftoppm and OCR with tesseract
        $pdftoppm = $this->resolveBinary('pdftoppm');
        $tesseract = $this->resolveBinary('tesseract');
        if ($pdftoppm && $tesseract) {
            $prefix = $filePath . '_ppm';
            $process = new Process([$pdftoppm, '-png', '-f', '1', '-l', '5', $filePath, $prefix]);
            $process->setTimeout(90);
            $process->run();

            if ($process->isSuccessful()) {
                $texts = [];
                // Collect generated PNGs (prefix-1.png, prefix-2.png, ...)
                $dir = dirname($filePath);
                $base = basename($prefix);
                $pattern = $dir . DIRECTORY_SEPARATOR . $base . '-*.png';
                foreach (glob($pattern) as $png) {
                    try {
                        $p = new Process([$tesseract, $png, 'stdout', '-l', 'spa+eng']);
                        $p->setTimeout(60);
                        $p->run();
                        if ($p->isSuccessful()) {
                            $texts[] = $p->getOutput();
                        }
                    } catch (\Throwable $e) {
                        // ignore individual page failures
                    } finally {
                        @unlink($png);
                    }
                }

                $joined = $this->normalizeText(implode("\n\n", array_filter($texts, fn($t) => trim($t) !== '')));
                if ($joined !== '') {
                    return [
                        'text' => $joined,
                        'metadata' => [
                            'detected_type' => 'application/pdf',
                            'engine' => 'pdftoppm+tesseract',
                        ],
                    ];
                }
            }
        }

        // Last-resort naive extractor (pure PHP, best-effort)
        $naive = $this->extractPdfNaively($filePath);
        if ($naive !== null && trim($naive) !== '') {
            return [
                'text' => $this->normalizeText($naive),
                'metadata' => [
                    'detected_type' => 'application/pdf',
                    'engine' => 'naive-parser',
                ],
            ];
        }

        throw new RuntimeException('No se encontró ningún motor para extraer texto de PDF.');
    }

    /**
     * Very naive pure-PHP PDF text extractor.
     * Parses BT/ET blocks and Tj/TJ operators to retrieve visible text.
     * Not robust, but better than failing when no engine is available.
     */
    private function extractPdfNaively(string $filePath): ?string
    {
        try {
            $contents = @file_get_contents($filePath);
            if ($contents === false || $contents === '') {
                return null;
            }

            // Attempt to find text drawing operators
            $textBlocks = [];
            if (preg_match_all('/BT(.*?)ET/s', $contents, $btMatches)) {
                foreach ($btMatches[1] as $block) {
                    $textBlocks[] = $block;
                }
            } else {
                // If no BT/ET blocks found, use whole content as a fallback region
                $textBlocks[] = $contents;
            }

            $out = [];
            foreach ($textBlocks as $block) {
                // Match string literals used with Tj: (text) Tj
                if (preg_match_all('/\((?:\\\)|\\\(|\\\\|\\[0-7]{1,3}|[^()\\])*\)\s*Tj/s', $block, $matches)) {
                    foreach ($matches[0] as $m) {
                        if (preg_match('/\(((?:\\\)|\\\(|\\\\|\\[0-7]{1,3}|[^()\\])*)\)\s*Tj/s', $m, $mm)) {
                            $out[] = $this->pdfDecodeString($mm[1]);
                        }
                    }
                }

                // Match array of strings used with TJ: [ (a) (b) ] TJ
                if (preg_match_all('/\[((?:\s*\((?:\\\)|\\\(|\\\\|\\[0-7]{1,3}|[^()\\])*\)\s*-?\d*\s*)+)\]\s*TJ/s', $block, $matchesTJ)) {
                    foreach ($matchesTJ[1] as $arr) {
                        if (preg_match_all('/\((?:\\\)|\\\(|\\\\|\\[0-7]{1,3}|[^()\\])*\)/s', $arr, $strs)) {
                            foreach ($strs[0] as $s) {
                                $s = trim($s);
                                $s = preg_replace('/^\(|\)$/', '', $s);
                                $out[] = $this->pdfDecodeString($s);
                            }
                        }
                    }
                }
            }

            $joined = trim(implode("\n", array_filter($out, fn($v) => $v !== '')));
            return $joined;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function pdfDecodeString(string $text): string
    {
        // Replace escaped characters
        $text = str_replace(['\\n','\\r','\\t','\\f','\\b'], ["\n","\r","\t","\f","\b"], $text);
        $text = str_replace(['\\(','\\)','\\\\'], ['(',')','\\'], $text);

        // Handle octal escapes (\\ddd)
        $text = preg_replace_callback('/\\\d{1,3}/', function ($m) {
            $oct = substr($m[0], 1);
            $code = octdec($oct);
            if ($code === 0) return '';
            return chr($code);
        }, $text) ?? $text;

        // Remove stray non-printables
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $text);
        return trim($text);
    }

    private function extractWord(string $filePath, string $extension): array
    {
        if ($extension === 'docx') {
            return $this->extractDocx($filePath);
        }

        // Legacy .doc files are binary; we attempt a best-effort extraction.
        $contents = File::get($filePath);
        $text = $this->normalizeText($contents ?? '');

        return [
            'text' => $text,
            'metadata' => [
                'detected_type' => 'application/msword',
                'engine' => 'fallback-binary-cleanup',
            ],
        ];
    }

    private function extractDocx(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo DOCX.');
        }

        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($documentXml === false) {
            throw new RuntimeException('El archivo DOCX no contiene document.xml.');
        }

        $xml = simplexml_load_string($documentXml);
        if (! $xml) {
            throw new RuntimeException('No se pudo interpretar el contenido del DOCX.');
        }

        $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paragraphs = $xml->xpath('//w:p');
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $texts = [];
            foreach ($paragraph->xpath('.//w:t') as $node) {
                $texts[] = (string) $node;
            }
            if (! empty($texts)) {
                $lines[] = implode('', $texts);
            }
        }

        return [
            'text' => $this->normalizeText(implode("\n", $lines)),
            'metadata' => [
                'detected_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'engine' => 'zip-xml',
            ],
        ];
    }

    private function extractSpreadsheet(string $filePath, string $extension): array
    {
        if (class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                $sheets = [];

                foreach ($spreadsheet->getAllSheets() as $sheet) {
                    $rows = [];
                    foreach ($sheet->getRowIterator() as $row) {
                        $cellValues = [];
                        foreach ($row->getCellIterator() as $cell) {
                            $cellValues[] = trim((string) $cell->getCalculatedValue());
                        }
                        $rows[] = trim(implode("\t", array_filter($cellValues, fn ($value) => $value !== '')));
                    }
                    $rows = array_filter($rows, fn ($rowText) => $rowText !== '');
                    $sheets[] = [
                        'name' => $sheet->getTitle(),
                        'rows' => $rows,
                    ];
                }

                $lines = [];
                foreach ($sheets as $sheet) {
                    $lines[] = "# Hoja: " . $sheet['name'];
                    $lines = array_merge($lines, $sheet['rows']);
                }

                return [
                    'text' => $this->normalizeText(implode("\n", $lines)),
                    'metadata' => [
                        'detected_type' => 'spreadsheet',
                        'engine' => 'phpoffice/phpspreadsheet',
                        'sheet_count' => count($sheets),
                    ],
                ];
            } catch (\Throwable $exception) {
                // Fallback manual extraction below.
            }
        }

        if ($extension === 'csv') {
            $text = $this->normalizeText(File::get($filePath) ?? '');
            return [
                'text' => $text,
                'metadata' => [
                    'detected_type' => 'text/csv',
                    'engine' => 'csv-plain',
                ],
            ];
        }

        return $this->extractXlsxManually($filePath);
    }

    private function extractXlsxManually(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo XLSX.');
        }

        $sharedStrings = [];
        $shared = $zip->getFromName('xl/sharedStrings.xml');
        if ($shared !== false) {
            $xml = simplexml_load_string($shared);
            if ($xml) {
                $xml->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                foreach ($xml->xpath('//s:si') as $stringItem) {
                    $pieces = [];
                    foreach ($stringItem->xpath('.//s:t') as $node) {
                        $pieces[] = (string) $node;
                    }
                    $sharedStrings[] = implode('', $pieces);
                }
            }
        }

        $sheetSummaries = [];
        $lines = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];
            if (! str_starts_with($name, 'xl/worksheets/sheet')) {
                continue;
            }

            $sheetContent = $zip->getFromIndex($i);
            if ($sheetContent === false) {
                continue;
            }

            $xml = simplexml_load_string($sheetContent);
            if (! $xml) {
                continue;
            }

            $xml->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rows = [];
            foreach ($xml->sheetData->row as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    $values[] = $this->resolveSpreadsheetValue($cell, $sharedStrings);
                }
                $rows[] = trim(implode("\t", array_filter($values, fn ($value) => $value !== '')));
            }

            $rows = array_filter($rows, fn ($rowText) => $rowText !== '');
            $sheetSummaries[] = [
                'name' => $name,
                'rows' => count($rows),
            ];
            if (! empty($rows)) {
                $lines[] = '# Hoja: ' . $name;
                $lines = array_merge($lines, $rows);
            }
        }

        $zip->close();

        return [
            'text' => $this->normalizeText(implode("\n", $lines)),
            'metadata' => [
                'detected_type' => 'spreadsheet',
                'engine' => 'zip-xml',
                'sheets' => $sheetSummaries,
            ],
        ];
    }

    private function resolveSpreadsheetValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');
        if ($type === 's') {
            $index = (int) ($cell->v ?? 0);
            return $sharedStrings[$index] ?? '';
        }

        if ($type === 'inlineStr') {
            return trim((string) ($cell->is->t ?? ''));
        }

        if ($type === 'b') {
            return ((string) $cell->v) === '1' ? 'TRUE' : 'FALSE';
        }

        return trim((string) ($cell->v ?? ''));
    }

    private function extractImage(string $filePath): array
    {
        if (class_exists('thiagoalessio\\TesseractOCR\\TesseractOCR')) {
            try {
                $ocr = new \thiagoalessio\TesseractOCR\TesseractOCR($filePath);
                $ocr->lang('spa', 'eng');
                $text = $ocr->run();

                $normalized = $this->normalizeText($text);
                // If OCR yields too little text, try OpenAI Vision fallback
                if ($normalized === '' || mb_strlen($normalized) < 20) {
                    $vision = $this->tryOpenAiVision($filePath);
                    if ($vision !== null) {
                        return $vision;
                    }
                }

                return [
                    'text' => $normalized,
                    'metadata' => [
                        'detected_type' => 'image/ocr',
                        'engine' => 'tesseract-php',
                    ],
                ];
            } catch (\Throwable $exception) {
                // Fallback to CLI below.
            }
        }

        $binary = $this->resolveBinary('tesseract');
        if (! $binary) {
            // If no OCR engine is available, try OpenAI Vision before giving up
            $vision = $this->tryOpenAiVision($filePath);
            if ($vision !== null) {
                return $vision;
            }

            throw new RuntimeException('No se encontró un motor OCR disponible para imágenes.');
        }

        $tempOutput = $filePath . '_ocr';
        $process = new Process([$binary, $filePath, $tempOutput, '-l', 'spa+eng']);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            // As a last resort on OCR failure, try OpenAI Vision
            $vision = $this->tryOpenAiVision($filePath);
            if ($vision !== null) {
                return $vision;
            }

            throw new RuntimeException('No se pudo ejecutar OCR: ' . $process->getErrorOutput());
        }

        $textFile = $tempOutput . '.txt';
        $text = File::exists($textFile) ? File::get($textFile) : '';

        if (File::exists($textFile)) {
            File::delete($textFile);
        }

        $normalized = $this->normalizeText($text);
        if ($normalized === '' || mb_strlen($normalized) < 20) {
            $vision = $this->tryOpenAiVision($filePath);
            if ($vision !== null) {
                return $vision;
            }
        }

        return [
            'text' => $normalized,
            'metadata' => [
                'detected_type' => 'image/ocr',
                'engine' => 'tesseract-cli',
            ],
        ];
    }

    private function normalizeText(string $text): string
    {
        $text = Str::of($text)
            ->replace(["\r\n", "\r"], "\n")
            ->replaceMatches('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ')
            ->replaceMatches('/[ \t\f\v]+/', ' ')
            ->replaceMatches('/\n{3,}/', "\n\n")
            ->trim()
            ->toString();

        return $text;
    }

    private function resolveBinary(string $binary): ?string
    {
        $process = Process::fromShellCommandline('command -v ' . escapeshellarg($binary));
        $process->setTimeout(5);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $path = trim($process->getOutput());

        return $path !== '' ? $path : null;
    }

    /**
     * Attempts to extract useful text from an image using OpenAI Vision when OCR is unavailable or insufficient.
     * Returns null if the API key is missing or any error occurs.
     *
     * @return array{text: string, metadata: array}|null
     */
    private function tryOpenAiVision(string $filePath): ?array
    {
        try {
            $apiKey = OpenAiConfig::apiKey();
            if (! is_string($apiKey) || trim($apiKey) === '') {
                return null;
            }

            $bytes = @File::get($filePath);
            if ($bytes === false) {
                return null;
            }

            // Determine mime type
            $mime = 'image/png';
            if (class_exists('finfo')) {
                $f = new \finfo(\FILEINFO_MIME_TYPE);
                $detected = $f->file($filePath);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
            }

            $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($bytes);

            $client = \OpenAI::client($apiKey);
            $response = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Extrae todo el texto legible y un breve resumen de esta imagen. Devuelve solo el texto extraído, sin viñetas ni formato adicional. Si no hay texto visible, describe brevemente el contenido en 2-3 oraciones.'
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => ['url' => $dataUrl],
                        ],
                    ],
                ]],
                'temperature' => 0.2,
            ]);

            $content = trim($response->choices[0]->message->content ?? '');
            if ($content === '') {
                return null;
            }

            return [
                'text' => $this->normalizeText($content),
                'metadata' => [
                    'detected_type' => 'image/vision',
                    'engine' => 'openai-gpt-4o-mini',
                ],
            ];
        } catch (\Throwable $e) {
            // Vision fallback failed; ignore and return null
            return null;
        }
    }
}
