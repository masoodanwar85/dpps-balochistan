<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['company_license_period_months', '12', 'integer', 'licensing', 'Company license period (months)', 'Length of a company license, in months.', true],
            ['dealer_license_period_months', '12', 'integer', 'licensing', 'Dealer license period (months)', 'Length of a dealer license, in months.', true],
            ['renewal_window_days', '60', 'integer', 'licensing', 'Renewal window (days)', 'Days before expiry when a renewal may be submitted.', true],
            ['min_technical_staff_company', '2', 'integer', 'licensing', 'Minimum technical staff (company)', 'Verified active technical staff required before a company license can be issued.', true],
            ['deficiency_reply_days', '15', 'integer', 'licensing', 'Deficiency reply (days)', 'Days allowed for a reply to a deficiency letter.', true],
            ['enforce_document_requirements', 'false', 'boolean', 'licensing', 'Enforce document requirements', 'When false, a license can be issued with documents still incomplete.', true],
            ['alert_red_days', '30', 'integer', 'alerts', 'Red alert (days)', 'Days remaining at which the expiry alert turns red.', true],
            ['alert_amber_days', '90', 'integer', 'alerts', 'Amber alert (days)', 'Days remaining at which the expiry alert turns amber.', true],
            ['max_upload_size_mb', '10', 'integer', 'general', 'Maximum upload size (MB)', 'Largest file size accepted for an upload.', true],
            ['company_license_no_pattern', 'DPP/C/{YYYY}/{SERIAL:4}{RENEWAL}', 'string', 'numbering', 'Company license number pattern', 'Pattern used when a company license number is issued.', true],
            ['dealer_license_no_pattern', 'DPP/D/{DISTRICT}/{YYYY}/{SERIAL:4}{RENEWAL}', 'string', 'numbering', 'Dealer license number pattern', 'Pattern used when a dealer license number is issued.', true],
            ['application_no_pattern', 'APP-{C/D}-{YYYY}-{SERIAL:4}', 'string', 'numbering', 'Application number pattern', 'Pattern used when an application number is assigned.', true],
            ['public_verify_base_url', 'https://<domain>/verify/', 'string', 'general', 'Public verification URL', 'Base URL placed in the certificate QR code.', false],
        ];

        foreach ($settings as [$key, $value, $dataType, $group, $label, $description, $editable]) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'data_type' => $dataType,
                    'group' => $group,
                    'label' => $label,
                    'description' => $description,
                    'is_ui_editable' => $editable,
                ],
            );
        }
    }
}
