<?php

namespace App\Console\Commands;

use App\Models\GoogleToken;
use App\Models\OrganizationGoogleToken;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class EncryptLegacyDriveTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'encrypt:drive-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt legacy Google Drive tokens stored as plain text';

    public function handle(): int
    {
        $this->info('Iniciando migración de tokens de Google Drive...');

        $googleTokenUpdates = $this->processGoogleTokens();
        $organizationTokenUpdates = $this->processOrganizationTokens();
        $totalUpdates = $googleTokenUpdates + $organizationTokenUpdates;

        $this->info("GoogleToken actualizados: {$googleTokenUpdates}");
        $this->info("OrganizationGoogleToken actualizados: {$organizationTokenUpdates}");
        $this->info("Total de registros actualizados: {$totalUpdates}");

        Log::info('encrypt:drive-tokens summary', [
            'google_tokens_updated' => $googleTokenUpdates,
            'organization_google_tokens_updated' => $organizationTokenUpdates,
            'total_updated' => $totalUpdates,
        ]);

        $this->info('Migración finalizada.');

        return self::SUCCESS;
    }

    private function processGoogleTokens(): int
    {
        $updates = 0;

        GoogleToken::query()->chunkById(100, function ($tokens) use (&$updates) {
            foreach ($tokens as $token) {
                if ($this->encryptModelAttributes($token, [
                    'access_token',
                    'refresh_token',
                    'recordings_folder_id',
                ])) {
                    $token->save();
                    $updates++;
                }
            }
        });

        return $updates;
    }

    private function processOrganizationTokens(): int
    {
        $updates = 0;

        OrganizationGoogleToken::query()->chunkById(100, function ($tokens) use (&$updates) {
            foreach ($tokens as $token) {
                if ($this->encryptModelAttributes($token, [
                    'access_token',
                    'refresh_token',
                ])) {
                    $token->save();
                    $updates++;
                }
            }
        });

        return $updates;
    }

    private function encryptModelAttributes(Model $model, array $attributes): bool
    {
        $updated = false;

        foreach ($attributes as $attribute) {
            $rawValue = $model->getRawOriginal($attribute);

            if ($rawValue === null) {
                continue;
            }

            try {
                Crypt::decryptString($rawValue);
            } catch (DecryptException $exception) {
                $value = $model->getAttribute($attribute);
                $model->setAttribute($attribute, $value);
                $updated = true;
            }
        }

        return $updated;
    }
}
