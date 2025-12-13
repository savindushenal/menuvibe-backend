<?php

namespace App\Console\Commands;

use App\Models\MenuEndpoint;
use Illuminate\Console\Command;

class GenerateEndpointQrCodes extends Command
{
    protected $signature = 'endpoints:generate-qr {--force : Regenerate all QR codes even if they exist}';
    protected $description = 'Generate QR codes for all endpoints that do not have one';

    public function handle()
    {
        $force = $this->option('force');
        
        if ($force) {
            $endpoints = MenuEndpoint::all();
            $this->info("Regenerating QR codes for all {$endpoints->count()} endpoints using FRONTEND_URL: " . config('app.frontend_url'));
        } else {
            $endpoints = MenuEndpoint::whereNull('qr_code_url')->orWhere('qr_code_url', '')->get();
            $this->info("Found {$endpoints->count()} endpoints without QR codes");
        }
        
        foreach ($endpoints as $endpoint) {
            $endpoint->generateQrCodeUrl();
            $this->line("Generated QR for: {$endpoint->name} ({$endpoint->short_code}) -> {$endpoint->short_url}");
        }
        
        $this->info('Done! All endpoints now have QR codes.');
        
        return 0;
    }
}
