<?php

namespace App\Services;

use App\Models\AiContainerJuCache;
use Illuminate\Support\Facades\Log;

class ContainerJuCacheService
{
    public function getCachedPayload(int $containerId): ?array
    {
        try {
            /** @var AiContainerJuCache|null $row */
            $row = AiContainerJuCache::where('container_id', $containerId)->first();
            return $row?->payload;
        } catch (\Throwable $e) {
            Log::warning('ContainerJuCacheService:getCachedPayload failed', [
                'container_id' => $containerId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function setCachedPayload(int $containerId, array $payload): ?string
    {
        try {
            $row = AiContainerJuCache::firstOrNew(['container_id' => $containerId]);
            $row->payload = $payload;
            $row->save();

            return $row->checksum;
        } catch (\Throwable $e) {
            Log::error('ContainerJuCacheService:setCachedPayload failed', [
                'container_id' => $containerId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getChecksum(int $containerId): ?string
    {
        try {
            /** @var AiContainerJuCache|null $row */
            $row = AiContainerJuCache::where('container_id', $containerId)->first();
            return $row?->checksum;
        } catch (\Throwable $e) {
            Log::warning('ContainerJuCacheService:getChecksum failed', [
                'container_id' => $containerId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
