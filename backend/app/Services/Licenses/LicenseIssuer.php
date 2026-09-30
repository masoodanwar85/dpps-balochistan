<?php

namespace App\Services\Licenses;

use App\Models\ApplicationStageLog;
use App\Models\Company;
use App\Models\CompanyProduct;
use App\Models\Dealer;
use App\Models\License;
use App\Models\LicenseApplication;
use App\Models\LicenseStatusHistory;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Notifications\InAppNotifications;
use App\Support\SettingValue;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LicenseIssuer
{
    public function __construct(
        private ActivityLogger $logger,
        private LicenseNumbers $numbers,
        private LicenseReadiness $readiness,
        private InAppNotifications $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function issue(LicenseApplication $application, array $data, User $actor): License
    {
        if (! $application->isOpen()) {
            throw ValidationException::withMessages([
                'status' => ['This application is closed.'],
            ]);
        }

        $application->loadMissing('currentStage');

        if ($application->currentStage?->code !== 'issuance') {
            throw ValidationException::withMessages([
                'stage' => ['The license is issued from the issuance stage.'],
            ]);
        }

        $blockers = $this->readiness->blockers($application);

        if ($blockers !== []) {
            throw ValidationException::withMessages([
                'issuance' => $blockers,
            ]);
        }

        $dates = $this->readiness->dates($application);
        $warnings = $this->readiness->enforced() ? [] : $this->warnings($application, $dates['valid_to']);

        if ($warnings !== [] && empty($data['confirm_warnings'])) {
            throw new LicenseWarningException($warnings);
        }

        $token = Str::random(40);
        $qr = $this->qr($token);
        $number = $this->numbers->next($application);
        $path = 'certificates/'.$token.'.pdf';
        $pdf = Pdf::loadView('pdfs.license-certificate', [
            'licenseNo' => $number,
            'applicant' => $application->applicantName(),
            'kind' => $application->licensable_type === 'dealer' ? 'Pesticide dealer' : 'Pesticide company',
            'validFrom' => $dates['valid_from']->format('d-m-Y'),
            'validTo' => $dates['valid_to']->format('d-m-Y'),
            'qr' => $qr,
        ])->output();

        if (Storage::disk('local')->put($path, $pdf) === false) {
            throw ValidationException::withMessages([
                'certificate' => ['The certificate could not be stored.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($application, $actor, $data, $dates, $number, $path, $token, $warnings) {
                $this->supersede($application, $number, $actor);
                $outstanding = $this->readiness->outstandingRequired($application);
                $license = License::query()->create([
                    'license_no' => $number,
                    'licensable_type' => $application->licensable_type,
                    'licensable_id' => $application->licensable_id,
                    'application_id' => $application->id,
                    'license_kind' => $application->application_type === 'renewal' ? 'renewal' : ($application->application_type === 'restoration' ? 'restoration' : 'registration'),
                    'renewal_count' => $this->numbers->renewalCount($application),
                    'valid_from' => $dates['valid_from']->toDateString(),
                    'valid_to' => $dates['valid_to']->toDateString(),
                    'issued_at' => now(),
                    'issued_by' => $actor->id,
                    'status' => 'active',
                    'certificate_path' => $path,
                    'verification_token' => $token,
                    'documents_status' => $outstanding === 0 ? 'complete' : 'incomplete',
                    'documents_completed_at' => $outstanding === 0 ? now() : null,
                    'issued_with_enforcement' => $this->readiness->enforced(),
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                if ($application->licensable_type === 'company') {
                    CompanyProduct::query()
                        ->where('company_id', $application->licensable_id)
                        ->where('status', 'approved')
                        ->whereNull('approved_in_license_id')
                        ->update([
                            'approved_in_license_id' => $license->id,
                            'updated_by' => $actor->id,
                            'updated_at' => now(),
                        ]);
                }

                $log = $application->stageLogs()->whereNull('completed_at')->latest('id')->first();

                if ($log instanceof ApplicationStageLog) {
                    $log->fill([
                        'completed_at' => now(),
                        'acted_by' => $actor->id,
                        'outcome' => 'completed',
                    ]);
                    $log->save();
                }

                $application->status = 'issued';
                $application->updated_by = $actor->id;
                $application->save();
                $this->activateParty($application, $actor);

                if ($warnings !== []) {
                    $this->logger->log(
                        'warning_overridden',
                        'Warning confirmed: '.$data['warning_reason'],
                        'license',
                        $license->id,
                        $actor,
                        null,
                        ['warnings' => $warnings, 'warning_reason' => $data['warning_reason']],
                    );
                }

                $this->logger->log(
                    'issued',
                    'Issued license '.$license->license_no,
                    'license',
                    $license->id,
                    $actor,
                );
                $this->notifications->licenseIssued($license);

                return $license;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    /**
     * @return list<string>
     */
    private function warnings(LicenseApplication $application, Carbon $validTo): array
    {
        $assessment = $this->readiness->assessment($application);

        return is_array($assessment['warnings']) ? $assessment['warnings'] : [];
    }

    private function supersede(LicenseApplication $application, string $number, User $actor): void
    {
        $previous = License::query()
            ->where('licensable_type', $application->licensable_type)
            ->where('licensable_id', $application->licensable_id)
            ->whereNotIn('status', ['superseded', 'cancelled'])
            ->get();

        foreach ($previous as $license) {
            $from = $license->status;
            $license->status = 'superseded';
            $license->updated_by = $actor->id;
            $license->save();
            LicenseStatusHistory::query()->create([
                'license_id' => $license->id,
                'from_status' => $from,
                'to_status' => 'superseded',
                'reason' => 'Superseded by '.$number.'.',
                'effective_date' => now()->toDateString(),
                'changed_by' => $actor->id,
            ]);
            $this->logger->log(
                'updated',
                'Superseded by '.$number,
                'license',
                $license->id,
                $actor,
                ['status' => $from],
                ['status' => 'superseded'],
            );
        }
    }

    private function activateParty(LicenseApplication $application, User $actor): void
    {
        $party = $application->licensable_type === 'company'
            ? Company::query()->find($application->licensable_id)
            : Dealer::query()->find($application->licensable_id);

        if ($party === null || in_array($party->status, ['suspended', 'cancelled'], true)) {
            return;
        }

        $party->status = 'active';
        $party->updated_by = $actor->id;
        $party->save();
    }

    private function qr(string $token): string
    {
        $result = (new Builder(
            writer: new PngWriter,
            data: $this->verificationUrl($token),
            encoding: new Encoding('ISO-8859-1'),
            size: 180,
            margin: 8,
        ))->build();

        return $result->getDataUri();
    }

    private function verificationUrl(string $token): string
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
}
