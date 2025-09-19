<?php

namespace App\Support;

class OpenAiConfig
{
    public static function apiKey(): ?string
    {
        $key = config('openai.api_key');
        if (is_string($key) && trim($key) !== '') {
            return trim($key);
        }

        $key = config('services.openai.api_key');
        if (is_string($key) && trim($key) !== '') {
            return trim($key);
        }

        return null;
    }
}
