<?php

namespace App\Services\Licenses;

use App\Models\Dealer;
use App\Models\DealerOwner;
use App\Models\LicenseApplication;
use App\Models\Person;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class DealerCertificate
{
    public function __construct(private CertificateQr $qr) {}

    public function pdf(Dealer $dealer, ?LicenseApplication $application, string $licenseNo, string $validFrom, string $validTo, string $token, string $dispatchNo = ''): string
    {
        $dealer->loadMissing('district');
        $owner = DealerOwner::query()
            ->where('dealer_id', $dealer->id)
            ->whereNull('end_date')
            ->orderBy('start_date')
            ->with('person')
            ->first();
        $person = $owner?->person;
        $dated = $application?->received_at ?? $application?->submitted_at;

        $pdf = Pdf::loadView('pdfs.dealer-certificate', [
            'licenseNo' => $licenseNo,
            'dispatchNo' => $dispatchNo,
            'dated' => $validFrom,
            'applicationNo' => (string) ($application->application_no ?? ''),
            'applicationDate' => $dated?->format('d-m-Y') ?? '',
            'name' => $person instanceof Person ? (string) $person->full_name : (string) $dealer->shop_name,
            'father' => $person instanceof Person ? (string) ($person->father_name ?? '') : '',
            'cnic' => $this->cnic($person),
            'district' => (string) ($dealer->district?->name ?? ''),
            'validFrom' => $validFrom,
            'validTo' => $validTo,
            'address' => trim(preg_replace('/\s+/', ' ', (string) $dealer->business_address) ?? ''),
            'phone' => $this->phones($dealer, $person),
            'photo' => $this->photo($dealer->photo_path),
            'qr' => $this->qr->dataUri($token),
            'bug' => $this->asset('dealer/ladybug.png'),
            'agri' => $this->asset('dealer/agri-deptt-logo.jpeg'),
            'roundel' => $this->asset('dealer/plant-protection-logo.png'),
            'arch' => $this->asset('dealer/arch.png'),
            'terms' => $this->asset('dealer/terms.png'),
            'fontDir' => resource_path('certificates/fonts'),
        ])->setPaper([0, 0, 841.89, 595.28]);
        $pdf->setOption('chroot', [
            resource_path('certificates'),
            resource_path('views'),
        ]);

        return $pdf->output();
    }

    private function cnic(?Person $person): string
    {
        if (! $person instanceof Person || $person->cnic_pending) {
            return '';
        }

        $digits = preg_replace('/\D/', '', (string) $person->cnic) ?? '';
        if (strlen($digits) !== 13) {
            return (string) $person->cnic;
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12, 1);
    }

    private function phones(Dealer $dealer, ?Person $person): string
    {
        $numbers = [];
        foreach ([$dealer->mobile, $person?->mobile, $person?->alt_mobile] as $number) {
            $number = trim((string) $number);
            if ($number !== '' && ! in_array($number, $numbers, true)) {
                $numbers[] = $number;
            }
        }

        return implode(' / ', $numbers);
    }

    private function photo(?string $path): ?string
    {
        if (! is_string($path) || $path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => null,
        };
        $bytes = $mime === null ? null : Storage::disk('local')->get($path);

        return is_string($bytes) && $bytes !== '' ? 'data:'.$mime.';base64,'.base64_encode($bytes) : null;
    }

    private function asset(string $name): string
    {
        $bytes = file_get_contents(resource_path('certificates/'.$name));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = $ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($bytes === false ? '' : $bytes);
    }
}
