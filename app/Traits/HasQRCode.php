<?php

namespace App\Traits;

use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;

trait HasQRCode
{
    /**
     * Generate QR code for this model
     */
    public function generateQR(int $size = 300): string
    {
        return QrCodeGenerator::format('png')
            ->size($size)
            ->generate($this->qr_url);
    }

    /**
     * Get QR code URL
     */
    public function getQrUrlAttribute(): string
    {
        if (method_exists($this, 'getQRUrl')) {
            return $this->getQRUrl();
        }

        // Default implementation for Menu model
        if ($this instanceof \App\Models\Menu) {
            return route('menu.view', [
                'franchise' => $this->location->franchise->slug ?? 'default',
                'location' => $this->location->slug ?? $this->location->id,
                'menu' => $this->slug ?? $this->id,
            ]);
        }

        return url('/');
    }

    /**
     * Get or create QR code record
     */
    public function qrCode()
    {
        return $this->morphOne(\App\Models\QRCode::class, 'qrcodeable');
    }

    /**
     * Generate and store QR code
     */
    public function createQRCode(int $size = 300): \App\Models\QRCode
    {
        $code = $this->generateQR($size);

        return $this->qrCode()->updateOrCreate([], [
            'code' => $code,
            'url' => $this->qr_url,
            'format' => 'png',
            'size' => $size,
        ]);
    }
}
