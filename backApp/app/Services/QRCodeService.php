<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Log;

class QRCodeService
{
    public function generate($url)
    {
        try {
            $qrCode = QrCode::format('svg')
                ->size(200)
                ->errorCorrection('H')
                ->generate($url);

            return base64_encode($qrCode);

        } catch (\Exception $e) {
            Log::error('QRCodeService::generate - Erreur', [
                'message' => $e->getMessage()
            ]);

            // Fallback
            $qrCode = QrCode::format('svg')
                ->size(150)
                ->errorCorrection('H')
                ->generate($url);

            return base64_encode($qrCode);
        }
    }
}