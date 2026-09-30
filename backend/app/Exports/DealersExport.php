<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DealersExport implements FromCollection, WithHeadings
{
    /**
     * @param  Collection<int, list<string>>  $rows
     */
    public function __construct(private Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Code', 'Shop Name', 'District', 'Expiry', 'Status'];
    }
}
