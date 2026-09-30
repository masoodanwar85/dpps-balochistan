<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use App\Models\Province;
use App\Models\Qualification;
use Illuminate\Database\Seeder;

class LookupSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Punjab',
            'Sindh',
            'KP',
            'Balochistan',
            'Islamabad',
            'GB',
            'AJK',
        ] as $name) {
            Province::query()->updateOrCreate(
                ['name' => $name],
                ['is_active' => true],
            );
        }

        foreach ([
            ['Matric', false],
            ['Intermediate (FSc)', false],
            ['Diploma in Agriculture', true],
            ['B.Sc Agriculture', true],
            ['B.Sc (Hons) Agriculture', true],
            ['M.Sc Agriculture', true],
            ['M.Sc (Hons) Agriculture', true],
            ['M.Phil Agriculture', true],
            ['PhD Agriculture', true],
            ['B.Sc (Other)', false],
            ['M.Sc (Other)', false],
            ['Other', false],
        ] as [$name, $agriculture]) {
            Qualification::query()->updateOrCreate(
                ['name' => $name],
                [
                    'is_agriculture_degree' => $agriculture,
                    'is_active' => true,
                ],
            );
        }

        foreach ([
            ['CNIC copy', 'cnic', 'person', true],
            ['Degree certificate', 'qualification', 'person', false],
            ['Appointment letter', 'application', 'person', false],
            ['Certificate of Incorporation', 'other', 'company', false],
            ['Memorandum & Articles of Association', 'other', 'company', false],
            ['List of Directors', 'other', 'company', false],
            ['NTN / Income tax certificate', 'other', 'company', false],
            ['Bank certificate / statement', 'other', 'any', false],
            ['PCPA / CropLife membership', 'other', 'company', true],
            ['Provincial registration certificate', 'license', 'company', true],
            ['Previous license certificate', 'license', 'any', true],
            ['Audit report', 'other', 'company', false],
            ['Agreement / Purchase agreement', 'agreement', 'any', true],
            ['Partnership deed', 'agreement', 'any', false],
            ['Import certificate', 'other', 'company', true],
            ['DPP product registration', 'license', 'company', true],
            ['Product label / leaflet', 'other', 'company', false],
            ['Affidavit / Undertaking', 'agreement', 'any', false],
            ['Treasury challan', 'challan', 'any', false],
            ['Application form', 'application', 'any', false],
            ['Deficiency letter', 'correspondence', 'any', false],
            ['Correspondence', 'correspondence', 'any', false],
            ['Other', 'other', 'any', false],
        ] as [$name, $category, $appliesTo, $hasExpiry]) {
            DocumentType::query()->updateOrCreate(
                ['name' => $name],
                [
                    'category' => $category,
                    'applies_to' => $appliesTo,
                    'has_expiry' => $hasExpiry,
                    'is_active' => true,
                ],
            );
        }
    }
}
