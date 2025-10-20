<?php

use App\Models\AiDocument;
use App\Jobs\ProcessAiDocumentJob;

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$doc = AiDocument::find(60);
$doc->update(['processing_status' => 'pending', 'extracted_text' => '']);
ProcessAiDocumentJob::dispatch($doc->id);

echo 'Job creado para reprocesar documento ID: 60' . PHP_EOL;
