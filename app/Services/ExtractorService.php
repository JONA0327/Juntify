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
        // OCR configuration via environment (with safe defaults)
        $ocrDpi = (int) (env('OCR_PDF_DPI', 300));
        if ($ocrDpi < 72) { $ocrDpi = 72; }
        if ($ocrDpi > 600) { $ocrDpi = 600; }

        $ocrFirstPage = (int) (env('OCR_PDF_FIRST_PAGE', 1));
        if ($ocrFirstPage < 1) { $ocrFirstPage = 1; }
        $ocrMaxPages = (int) (env('OCR_PDF_MAX_PAGES', 5));
        if ($ocrMaxPages < 1) { $ocrMaxPages = 1; }
        if ($ocrMaxPages > 20) { $ocrMaxPages = 20; }
        $ocrLastPage = $ocrFirstPage + $ocrMaxPages - 1;

        $ocrLangsRaw = trim((string) env('OCR_LANGUAGES', 'spa+eng,eng,spa'));
        $ocrLangs = array_values(array_filter(array_map('trim', explode(',', $ocrLangsRaw)), fn($v) => $v !== ''));
        if (empty($ocrLangs)) { $ocrLangs = ['spa+eng', 'eng', 'spa']; }

        // Prefer native PHP libraries if they are installed.
        if (class_exists('Smalot\\PdfParser\\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filePath);
                $text = $this->normalizeText($pdf->getText());

                // If smalot returns empty text (common in scanned PDFs), continue to fallbacks
                if ($text !== '') {
                    return [
                        'text' => $text,
                        'metadata' => [
                            'detected_type' => 'application/pdf',
                            'pages' => count($pdf->getPages()),
                            'engine' => 'smalot/pdfparser',
                        ],
                    ];
                }
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

            // If pdftotext failed, don't stop—continue with OCR fallbacks
            if ($process->isSuccessful()) {
                $text = File::exists($outputFile) ? File::get($outputFile) : '';
                if ($text === false) {
                    $text = '';
                }
                if ($outputFile && File::exists($outputFile)) {
                    File::delete($outputFile);
                }

                $normalized = $this->normalizeText($text);
                // If text is non-empty, return; otherwise try OCR fallbacks
                if ($normalized !== '') {
                    return [
                        'text' => $normalized,
                        'metadata' => [
                            'detected_type' => 'application/pdf',
                            'engine' => 'pdftotext-cli',
                        ],
                    ];
                }
            } else {
                // Clean up possible output file if process failed
                if ($outputFile && File::exists($outputFile)) {
                    File::delete($outputFile);
                }
            }
        }

        // Ghostscript fallback using txtwrite device (if available)
        $gs = $this->resolveBinary('gs');
        if ($gs) {
            try {
                // -q quiet, -dSAFER safety, -sDEVICE=txtwrite write text to stdout (-o -)
                $process = new Process([$gs, '-q', '-dSAFER', '-sDEVICE=txtwrite', '-o', '-', $filePath]);
                $process->setTimeout(90);
                $process->run();
                if ($process->isSuccessful()) {
                    $text = $process->getOutput();
                    $normalized = $this->normalizeText($text ?? '');
                    if ($normalized !== '') {
                        return [
                            'text' => $normalized,
                            'metadata' => [
                                'detected_type' => 'application/pdf',
                                'engine' => 'ghostscript-txtwrite',
                            ],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // ignore and continue to next fallback
            }
        }

        // Ghostscript -> PNG fallback + OCR (if pdftoppm not available)
        if ($gs) {
            try {
                $dir = dirname($filePath);
                $prefix = $filePath . '_gs';
                // Render limited pages at configurable DPI for better OCR quality
                $process = new Process([
                    $gs,
                    '-q',
                    '-dSAFER',
                    '-sDEVICE=png16m',
                    '-r' . $ocrDpi,
                    '-dFirstPage=' . $ocrFirstPage,
                    '-dLastPage=' . $ocrLastPage,
                    '-o', $prefix . '-%03d.png',
                    $filePath
                ]);
                $process->setTimeout(120);
                $process->run();

                $generated = glob($prefix . '-*.png') ?: [];
                if (!empty($generated)) {
                    $texts = [];
                    $tesseract = $this->resolveBinary('tesseract');
                    foreach ($generated as $png) {
                        try {
                            if ($tesseract) {
                                // Try multiple language options for robustness
                                foreach ($ocrLangs as $lang) {
                                    $p = new Process([$tesseract, $png, 'stdout', '-l', $lang]);
                                    $p->setTimeout(60);
                                    $p->run();
                                    if ($p->isSuccessful() && trim($p->getOutput()) !== '') {
                                        $texts[] = $p->getOutput();
                                        break;
                                    }
                                }
                            } else {
                                // No tesseract: try OpenAI Vision per page
                                $vision = $this->tryOpenAiVision($png);
                                if ($vision !== null) {
                                    $texts[] = $vision['text'] ?? '';
                                }
                            }
                        } catch (\Throwable $e) {
                            // ignore per-page errors
                        } finally {
                            @unlink($png);
                        }
                        if (count($texts) >= 5) { // limit to first 5 pages
                            break;
                        }
                    }

                    $joined = $this->normalizeText(implode("\n\n", array_filter($texts, fn ($t) => trim($t) !== '')));
                    if ($joined !== '') {
                        return [
                            'text' => $joined,
                            'metadata' => [
                                'detected_type' => 'application/pdf',
                                'engine' => $tesseract ? 'ghostscript+ocr-tesseract' : 'ghostscript+openai-vision',
                            ],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // ignore and continue
            }
        }

        // OCR fallback for PDFs: convert pages to images with pdftoppm and OCR with tesseract
        $pdftoppm = $this->resolveBinary('pdftoppm');
        $tesseract = $this->resolveBinary('tesseract');
        if ($pdftoppm && $tesseract) {
            $prefix = $filePath . '_ppm';
            // Use configurable DPI and page range for better OCR accuracy
            $process = new Process([$pdftoppm, '-png', '-r', (string) $ocrDpi, '-f', (string) $ocrFirstPage, '-l', (string) $ocrLastPage, $filePath, $prefix]);
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
                        // Try multiple languages in order of preference
                        $recognized = '';
                        foreach ($ocrLangs as $lang) {
                            $p = new Process([$tesseract, $png, 'stdout', '-l', $lang]);
                            $p->setTimeout(60);
                            $p->run();
                            if ($p->isSuccessful() && trim($p->getOutput()) !== '') {
                                $recognized = $p->getOutput();
                                break;
                            }
                        }
                        if ($recognized !== '') {
                            $texts[] = $recognized;
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
        if (class_exists('thiagoalessio\TesseractOCR\TesseractOCR')) {
            try {
                // Try spa+eng first, then eng, then spa
                $langsToTry = [['spa','eng'], ['eng'], ['spa']];
                $text = '';
                foreach ($langsToTry as $langs) {
                    try {
                        $ocr = new \thiagoalessio\TesseractOCR\TesseractOCR($filePath);
                        $ocr->lang(...$langs);
                        $text = $ocr->run();
                        if (is_string($text) && trim($text) !== '') {
                            break;
                        }
                    } catch (\Throwable $e) {
                        // try next
                    }
                }

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
        $langsToTry = ['spa+eng', 'eng', 'spa'];
        $ocrOk = false;
        $errMsg = '';
        foreach ($langsToTry as $lang) {
            $process = new Process([$binary, $filePath, $tempOutput, '-l', $lang]);
            $process->setTimeout(60);
            $process->run();
            if ($process->isSuccessful()) {
                $ocrOk = true;
                break;
            } else {
                $errMsg = $process->getErrorOutput();
            }
        }

        if (! $ocrOk) {
            // As a last resort on OCR failure, try OpenAI Vision
            $vision = $this->tryOpenAiVision($filePath);
            if ($vision !== null) {
                return $vision;
            }

            throw new RuntimeException('No se pudo ejecutar OCR: ' . $errMsg);
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
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        // 0) Environment overrides for explicit paths
        $envMap = [
            'tesseract' => env('TESSERACT_PATH'),
            'gs' => env('GHOSTSCRIPT_PATH'),
            'pdftoppm' => env('PDFTOPPM_PATH'),
            'pdftotext' => env('PDFTOTEXT_PATH'),
        ];
        if (isset($envMap[$binary]) && is_string($envMap[$binary]) && $envMap[$binary] !== '') {
            $p = $envMap[$binary];
            if (@file_exists($p)) {
                return $p;
            }
        }

        // Build candidate executable names per platform
        $candidates = [$binary];
        if ($isWindows) {
            if (!Str::endsWith($binary, '.exe')) {
                $candidates[] = $binary . '.exe';
            }
            if ($binary === 'gs') {
                // Ghostscript on Windows uses gswinXXc.exe
                array_unshift($candidates, 'gswin64c.exe', 'gswin32c.exe', 'gswin64.exe', 'gswin32.exe', 'gs.exe');
            } elseif ($binary === 'pdftotext') {
                array_unshift($candidates, 'pdftotext.exe');
            } elseif ($binary === 'pdftoppm') {
                array_unshift($candidates, 'pdftoppm.exe');
            } elseif ($binary === 'tesseract') {
                array_unshift($candidates, 'tesseract.exe');
            }
        }

        // 1) Try PATH lookup (which/where)
        foreach ($candidates as $name) {
            try {
                $proc = $isWindows
                    ? new Process(['where.exe', $name])
                    : new Process(['which', $name]);
                $proc->setTimeout(5);
                $proc->run();
                if ($proc->isSuccessful()) {
                    $out = trim($proc->getOutput());
                    // Take the first line if multiple
                    $line = trim(strtok($out, "\r\n"));
                    if ($line !== '' && @file_exists($line)) {
                        return $line;
                    }
                }
            } catch (\Throwable $e) {
                // continue
            }
        }

        // 2) Try common installation paths on Windows
        if ($isWindows) {
            $pathsToCheck = [];

            // Tesseract default installs
            if ($binary === 'tesseract') {
                $pathsToCheck[] = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
                $pathsToCheck[] = 'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe';
            }

            // Ghostscript default installs
            if ($binary === 'gs') {
                foreach (['C:\\Program Files\\gs\\*\\bin\\gswin64c.exe', 'C:\\Program Files (x86)\\gs\\*\\bin\\gswin32c.exe'] as $pattern) {
                    foreach (glob($pattern) ?: [] as $p) {
                        $pathsToCheck[] = $p;
                    }
                }
            }

            // Poppler default installs
            if (in_array($binary, ['pdftotext', 'pdftoppm'], true)) {
                $exe = $binary . '.exe';
                foreach ([
                    'C:\\Program Files\\poppler*\\bin\\' . $exe,
                    'C:\\Program Files (x86)\\poppler*\\bin\\' . $exe,
                    'C:\\poppler*\\bin\\' . $exe,
                ] as $pattern) {
                    foreach (glob($pattern) ?: [] as $p) {
                        $pathsToCheck[] = $p;
                    }
                }
            }

            foreach ($pathsToCheck as $p) {
                if (@file_exists($p)) {
                    return $p;
                }
            }
        }

        return null;
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
