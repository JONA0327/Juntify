<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDatabaseConnection extends Command
{
    protected $signature = 'db:check {--database=mysql}';
    protected $description = 'Check database connection';

    public function handle()
    {
        $database = $this->option('database');
        
        try {
            // Create a connection without specifying a database to list all databases
            $pdo = new \PDO(
                'mysql:host=' . config('database.connections.mysql.host') . ';port=' . config('database.connections.mysql.port'),
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password')
            );
            
            $this->info('✓ Connected to MySQL server successfully!');
            $this->line('Host: ' . config('database.connections.mysql.host'));
            $this->line('Port: ' . config('database.connections.mysql.port'));
            $this->line('');
            
            // Try to list databases
            $stmt = $pdo->query('SHOW DATABASES');
            $databases = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            $this->info('Available databases:');
            foreach ($databases as $db) {
                if (in_array($db, ['juntify', 'u191251575_juntify', 'juntify_new'])) {
                    $this->info('  ✓ ' . $db . ' (MATCHES)');
                } else {
                    $this->line('  - ' . $db);
                }
            }
            
            // Check for juntify-like databases
            $this->line('');
            $this->info('Checking for Juntify databases:');
            $juntifyDatabases = array_filter($databases, function($db) {
                return stripos($db, 'juntify') !== false;
            });
            
            if ($juntifyDatabases) {
                foreach ($juntifyDatabases as $db) {
                    $this->info('  Found: ' . $db);
                }
            } else {
                $this->warn('  No Juntify databases found!');
            }
            
        } catch (\Exception $e) {
            $this->error('✗ Connection failed: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
