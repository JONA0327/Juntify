<?php

namespace App\Services;

class AudioConverter
{
    /**
     * Return the provided path without performing any conversion.
     *
     * @param  string      $path          Path to the audio file
     * @param  string|null $extensionHint Optional extension hint (unused)
     * @return string                     The original path
     */
    public function convertWebmToMp3(string $path, ?string $extensionHint = null): string
    {
        return $path;
    }
}

