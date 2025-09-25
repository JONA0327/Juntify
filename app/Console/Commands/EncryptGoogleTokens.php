<?php

namespace App\Console\Commands;

use App\Models\GoogleToken;
use App\Models\OrganizationGoogleToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class EncryptGoogleTokens extends Command
{
    protected $signature = 'google:encrypt-existing-tokens';

    protected $description = 'Recorre los tokens de Google y asegura que sus valores estén cifrados.';

    public function handle(): int
    {
        $this->info('Iniciando cifrado de tokens de usuarios...');
        $userCount = $this->encryptGoogleTokens();

        $this->info('Iniciando cifrado de tokens de organizaciones...');
        $organizationCount = $this->encryptOrganizationTokens();

        $this->info(sprintf('Tokens actualizados: usuarios=%d, organizaciones=%d', $userCount, $organizationCount));

        return self::SUCCESS;
    }

    protected function encryptGoogleTokens(): int
    {
        $updated = 0;

        GoogleToken::chunkById(100, function ($tokens) use (&$updated) {
            foreach ($tokens as $token) {
                $needsAccessEncryption = $this->needsEncryption($token->getRawOriginal('access_token'));
                $needsRefreshEncryption = $this->needsEncryption($token->getRawOriginal('refresh_token'));

                if (! $needsAccessEncryption && ! $needsRefreshEncryption) {
                    continue;
                }

                if ($needsAccessEncryption) {
                    $token->access_token = $token->access_token;
                }

                if ($needsRefreshEncryption && $token->refresh_token !== null) {
                    $token->refresh_token = $token->refresh_token;
                }

                $token->save();
                $updated++;
            }
        });

        return $updated;
    }

    protected function encryptOrganizationTokens(): int
    {
        $updated = 0;

        OrganizationGoogleToken::chunkById(100, function ($tokens) use (&$updated) {
            foreach ($tokens as $token) {
                $needsAccessEncryption = $this->needsEncryption($token->getRawOriginal('access_token'));
                $needsRefreshEncryption = $this->needsEncryption($token->getRawOriginal('refresh_token'));

                if (! $needsAccessEncryption && ! $needsRefreshEncryption) {
                    continue;
                }

                if ($needsAccessEncryption) {
                    $token->access_token = $token->access_token;
                }

                if ($needsRefreshEncryption && $token->refresh_token !== null) {
                    $token->refresh_token = $token->refresh_token;
                }

                $token->save();
                $updated++;
            }
        });

        return $updated;
    }

    protected function needsEncryption($value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (! is_string($value)) {
            return true;
        }

        try {
            Crypt::decryptString($value);
            return false;
        } catch (DecryptException $exception) {
            return true;
        } catch (\Throwable $exception) {
            return true;
        }
    }
}
