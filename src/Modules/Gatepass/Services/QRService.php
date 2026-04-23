<?php

namespace App\Modules\Gatepass\Services;

class QRService
{
    public function generate(string $gatepassNumber): string
    {
        $url = getenv('QR_SERVICE_URL') . '/generate?code=' . urlencode($gatepassNumber);

        return file_get_contents($url);
    }
}
