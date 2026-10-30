<?php

namespace App\Services\Licenses;

use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;

class CompanyCertificate
{
    public function __construct(private CertificateQr $qr) {}

    public function pdf(Company $company, string $licenseNo, string $validFrom, string $validTo, string $token, string $dispatchNo = ''): string
    {
        $pdf = Pdf::loadView('pdfs.company-certificate', [
            'name' => $company->name,
            'address' => trim(preg_replace('/\s+/', ' ', (string) $company->head_office_address) ?? ''),
            'email' => (string) $company->email,
            'phone' => (string) $company->mobile,
            'licenseNo' => $licenseNo,
            'dispatchNo' => $dispatchNo,
            'validFrom' => $validFrom,
            'validTo' => $validTo,
            'qr' => $this->qr->dataUri($token),
            'bug' => $this->asset('dealer/ladybug.png'),
            'agri' => $this->asset('dealer/agri-deptt-logo.jpeg'),
            'roundel' => $this->asset('dealer/plant-protection-logo.png'),
            'watermark' => $this->asset('company/watermark.png'),
            'arch' => $this->asset('company/arch.png'),
            'fontDir' => resource_path('certificates/fonts'),
        ])->setPaper([0, 0, 841.89, 595.28]);
        $pdf->setOption('chroot', [
            resource_path('certificates'),
            resource_path('views'),
        ]);

        return $pdf->output();
    }

    private function asset(string $name): string
    {
        $bytes = file_get_contents(resource_path('certificates/'.$name));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = $ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($bytes === false ? '' : $bytes);
    }
}
