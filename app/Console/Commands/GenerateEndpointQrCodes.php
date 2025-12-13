<?php

namespace App\Console\Commands;

use App\Models\MenuEndpoint;
use Illuminate\Console\Command;

class GenerateEndpointQrCodes extends Command
{
    protected $signature = 'endpoints:generate-qr';
    protected $description = 'Generate QR codes for all endpoints that do not have one';

    public function handle()
    {
        $endpoints = MenuEndpoint::whereNull('qr_code_url')->orWhere('qr_code_url', '')->get();
        
        $this->info("Found {$endpoints->count()} endpoints without QR codes");
        
        foreach ($endpoints as $endpoint) {
            $endpoint->generateQrCodeUrl();
            $this->line("Generated QR for: {$endpoint->name} ({$endpoint->short_code})");
        }
        
        $this->info('Done! All endpoints now have QR codes.');
        
        return 0;
    }
}
