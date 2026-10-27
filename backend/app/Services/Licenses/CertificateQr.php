<?php

namespace App\Services\Licenses;

use App\Models\Setting;
use App\Support\SettingValue;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;

class CertificateQr
{
    public function url(string $token): string
    {
        $base = 'https://<domain>/verify/';
        $setting = Setting::query()->where('key', 'public_verify_base_url')->first();

        if ($setting) {
            $value = SettingValue::typed($setting);

            if (is_string($value) && trim($value) !== '') {
                $base = trim($value);
            }
        }

        if (str_contains($base, '<domain>')) {
            $base = rtrim((string) config('app.frontend_url'), '/').'/verify/';
        }

        return str_ends_with($base, '/') ? $base.$token : $base.'/'.$token;
    }

    public function dataUri(string $token): string
    {
        $result = (new Builder(
            writer: new PngWriter,
            data: $this->url($token),
            encoding: new Encoding('ISO-8859-1'),
            size: 180,
            margin: 8,
        ))->build();

        return $result->getDataUri();
    }
}
