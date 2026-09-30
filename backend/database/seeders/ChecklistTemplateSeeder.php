<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChecklistTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $publisherId = User::query()->where('email', 'masoodanwar85@gmail.com')->value('id');

        foreach ([
            ['company', 'new', 'Company – New'],
            ['company', 'renewal', 'Company – Renewal'],
            ['dealer', 'new', 'Dealer – New'],
            ['dealer', 'renewal', 'Dealer – Renewal'],
        ] as [$entityType, $applicationType, $name]) {
            $template = ChecklistTemplate::query()->updateOrCreate(
                [
                    'entity_type' => $entityType,
                    'application_type' => $applicationType,
                    'version_no' => 1,
                ],
                [
                    'name' => $name,
                    'status' => 'published',
                    'published_at' => now(),
                    'published_by' => $publisherId,
                ],
            );

            $template->items()->delete();
            $sort = 1;

            foreach ($this->items() as $item) {
                if ($applicationType === 'new' && $item['renewal_only']) {
                    continue;
                }

                ChecklistItem::query()->create([
                    'template_id' => $template->id,
                    'sort_order' => $sort,
                    'annex_code' => $item['annex'],
                    'title' => $item['title'],
                    'description' => null,
                    'form_reference' => $item['form'],
                    'is_required' => $item['required'],
                    'requires_upload' => $item['annex'] !== 'V',
                    'allowed_file_types' => 'pdf,jpg,jpeg,png',
                    'max_files' => 5,
                    'attestation_required' => $item['attestation'],
                    'requires_validity_dates' => $item['annex'] === 'S',
                    'must_cover_license_period' => $item['covers'],
                    'portal_uploadable' => true,
                ]);

                $sort++;
            }
        }
    }

    /**
     * @return list<array{annex: string, title: string, form: ?string, required: bool, attestation: string, covers: bool, renewal_only: bool}>
     */
    private function items(): array
    {
        return [
            $this->item('A', 'Application on Form-C 12 (new) / Form-C 14 (renewal), signed by CEO/Director with full contact details', 'C-12/C-14', true, 'none', false, false),
            $this->item('B', 'Certificate of Incorporation', null, true, 'gazetted_officer', false, false),
            $this->item('C', 'Memorandum & Articles of Association', null, true, 'gazetted_officer', false, false),
            $this->item('D', 'List of Board of Directors with addresses and specimen signatures', null, true, 'gazetted_officer', false, false),
            $this->item('E', 'NTN and Income Tax certificates', null, true, 'gazetted_officer', false, false),
            $this->item('F', 'Bank certificate with one-year bank statement', null, true, 'gazetted_officer', false, false),
            $this->item('G', 'PCPA / CropLife membership certificate', null, true, 'gazetted_officer', false, false),
            $this->item('H', 'Registration certificates as distributor in Punjab, Sindh and KP', null, true, 'gazetted_officer', false, false),
            $this->item('I', 'Previous registration certificate(s) in Balochistan', null, true, 'gazetted_officer', false, true),
            $this->item('J', 'Audit report for the last period', null, true, 'gazetted_officer', false, true),
            $this->item('K', 'Evidence of commercial advertisement on agriculture websites', null, true, 'none', false, true),
            $this->item('L', 'Appointment of two technical staff / agriculture graduates', 'Form-6', true, 'gazetted_officer', true, false),
            $this->item('M', 'Pay record of technical and other staff', 'Form-7', true, 'none', false, true),
            $this->item('N', 'Sale data of products', 'Form-8', true, 'none', false, true),
            $this->item('O', 'Sale record of restricted pesticides', 'Form-8(B)', true, 'none', false, true),
            $this->item('P', 'Evidence of R&D and farmer advisory services', 'Form-9', true, 'none', false, true),
            $this->item('Q', 'Advisory slip sample (new) / used advisory slip books (renewal)', 'Form-10', true, 'none', false, false),
            $this->item('R', 'List of registered dealers, dealership certificates and model shops', 'Form-11', true, 'none', false, false),
            $this->item('S', 'Product import certificate or purchase agreement with importer', null, true, 'notary_public', true, false),
            $this->item('T', 'Products to be registered, with DPP registration, test reports, labels and leaflets', 'Form-12', true, 'none', false, false),
            $this->item('U', 'Approximate business volume (Rs M) for the next period', 'Form-12', true, 'none', false, false),
            $this->item('V', 'Pesticide samples and dummies', 'Form-12', true, 'none', false, false),
            $this->item('W', 'List of offices in Pakistan and Balochistan', 'Form-13', true, 'none', false, false),
            $this->item('X', 'List of capital / assets in Balochistan', 'Form-13', false, 'none', false, false),
            $this->item('Y', 'Samples drawn (fit / unfit)', 'Form-14', true, 'none', false, true),
            $this->item('Z', 'Medical check-up and treatment record of staff', 'Form-15', true, 'none', false, true),
            $this->item('AA', 'Bank treasury challan (fee / penalties)', null, true, 'none', false, false),
        ];
    }

    /**
     * @return array{annex: string, title: string, form: ?string, required: bool, attestation: string, covers: bool, renewal_only: bool}
     */
    private function item(string $annex, string $title, ?string $form, bool $required, string $attestation, bool $covers, bool $renewalOnly): array
    {
        return [
            'annex' => $annex,
            'title' => $title,
            'form' => $form,
            'required' => $required,
            'attestation' => $attestation,
            'covers' => $covers,
            'renewal_only' => $renewalOnly,
        ];
    }
}
