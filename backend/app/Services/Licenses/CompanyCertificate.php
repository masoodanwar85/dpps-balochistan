<?php

namespace App\Services\Licenses;

use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;

class CompanyCertificate
{
    public function __construct(private CertificateQr $qr) {}

    public function pdf(Company $company, string $licenseNo, string $validFrom, string $validTo, string $token, bool $form): string
    {
        return Pdf::loadView('pdfs.company-certificate', [
            'form' => $form,
            'name' => $company->name,
            'address' => trim(preg_replace('/\s+/', ' ', (string) $company->head_office_address) ?? ''),
            'email' => (string) $company->email,
            'phone' => (string) $company->mobile,
            'licenseNo' => $licenseNo,
            'validFrom' => $validFrom,
            'validTo' => $validTo,
            'qr' => $this->qr->dataUri($token),
            'page1' => $form ? null : $this->image('company-certificate-1.png'),
            'page2' => $form ? null : $this->image('company-certificate-2.png'),
        ])->setPaper([0, 0, 841.92, 595.32])->output();
    }

    private function image(string $name): string
    {
        $bytes = file_get_contents(resource_path('certificates/'.$name));

        return 'data:image/png;base64,'.base64_encode($bytes === false ? '' : $bytes);
    }
}
