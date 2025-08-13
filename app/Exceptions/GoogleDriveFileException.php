<?php

namespace App\Exceptions;

use RuntimeException;

class GoogleDriveFileException extends RuntimeException
{
    protected array $context;

    public function __construct(string $fileId, int $code, string $message, ?\Throwable $previous = null)
    {
        $this->context = [
            'file_id' => $fileId,
            'code' => $code,
            'message' => $message,
        ];

        parent::__construct(
            "Error handling Google Drive file {$fileId}: {$message}",
            $code,
            $previous
        );
    }

    public function getContext(): array
    {
        return $this->context;
    }
}

