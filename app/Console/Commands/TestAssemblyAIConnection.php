<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestAssemblyAIConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assemblyai:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test connection to AssemblyAI API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing AssemblyAI connection...');

        $apiKey = config('services.assemblyai.api_key');
        if (empty($apiKey)) {
            $this->error('AssemblyAI API key not configured');
            return 1;
        }

        $this->info('API Key: ' . substr($apiKey, 0, 8) . '...');
        $this->info('Verify SSL: ' . (config('services.assemblyai.verify_ssl', true) ? 'true' : 'false'));
        $this->info('Timeout: ' . config('services.assemblyai.timeout', 300) . 's');
        $this->info('Connect Timeout: ' . config('services.assemblyai.connect_timeout', 60) . 's');

        try {
            $timeout = config('services.assemblyai.timeout', 120);
            $connectTimeout = config('services.assemblyai.connect_timeout', 30);

            $this->info("Testing connection with {$connectTimeout}s connect timeout and {$timeout}s total timeout...");

            $http = Http::timeout($timeout)->connectTimeout($connectTimeout)
                ->withHeaders([
                    'authorization' => $apiKey,
                ]);

            if (!config('services.assemblyai.verify_ssl', true)) {
                $http = $http->withoutVerifying();
                $this->info('SSL verification disabled');
            }

            $response = $http->get('https://api.assemblyai.com/v2/transcript/nonexistent');

            if ($response->status() === 404) {
                $this->info('✅ Connection successful! (Got expected 404 for test endpoint)');
                return 0;
            } else {
                $this->warn("Connection successful but got unexpected status: {$response->status()}");
                $this->line($response->body());
                return 0;
            }

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->error('❌ Connection failed: ' . $e->getMessage());

            if (str_contains($e->getMessage(), 'SSL')) {
                $this->warn('💡 Try setting ASSEMBLYAI_VERIFY_SSL=false in your .env file');
            }
            if (str_contains($e->getMessage(), 'timeout')) {
                $this->warn('💡 Try increasing ASSEMBLYAI_TIMEOUT and ASSEMBLYAI_CONNECT_TIMEOUT values');
            }

            return 1;
        } catch (\Exception $e) {
            $this->error('❌ Unexpected error: ' . $e->getMessage());
            return 1;
        }
    }
}
