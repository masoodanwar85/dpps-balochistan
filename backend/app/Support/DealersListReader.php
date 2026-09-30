<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

class DealersListReader
{
    /**
     * Unique trimmed districts from the "By District" sheet.
     * Case-only duplicates are combined. Tehsil values that are blank or numeric are skipped.
     *
     * @return list<array{name: string, tehsils: list<string>}>
     */
    public function districts(string $path): array
    {
        $rows = $this->rows($path);
        $groups = [];

        foreach ($rows as $row) {
            $district = trim((string) ($row['C'] ?? ''));
            $tehsil = trim((string) ($row['E'] ?? ''));

            if ($district === '' || str_starts_with(strtolower($district), 'district')) {
                continue;
            }

            $key = strtolower($district);
            $groups[$key]['names'][$district] = $district;

            if ($tehsil !== '' && ! ctype_digit($tehsil)) {
                $groups[$key]['tehsils'][$tehsil] = $tehsil;
            }
        }

        $districts = [];

        foreach ($groups as $group) {
            $districts[] = [
                'name' => $this->preferredName($group['names']),
                'tehsils' => array_values($group['tehsils'] ?? []),
            ];
        }

        usort($districts, fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        return $districts;
    }

    /**
     * @param  array<string, string>  $names
     */
    private function preferredName(array $names): string
    {
        $values = array_values($names);

        usort($values, function (string $left, string $right): int {
            return $this->uppercaseCount($right) <=> $this->uppercaseCount($left)
                ?: strlen($right) <=> strlen($left);
        });

        return $values[0];
    }

    private function uppercaseCount(string $value): int
    {
        preg_match_all('/[A-Z]/', $value, $matches);

        return count($matches[0]);
    }

    /**
     * @return list<array<string, string>>
     */
    private function rows(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open the dealers workbook.');
        }

        $shared = $this->sharedStrings($zip);
        $sheetPath = $this->sheetPath($zip, 'By District');
        $xml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('The By District sheet could not be read.');
        }

        $sheet = simplexml_load_string($xml);
        $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];

        foreach ($sheet->xpath('//m:sheetData/m:row') as $row) {
            $row->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $values = [];

            foreach ($row->xpath('m:c') as $cell) {
                $cell->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $reference = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $reference);
                $type = (string) $cell['t'];
                $value = $cell->xpath('m:v');
                $raw = isset($value[0]) ? (string) $value[0] : '';

                if ($raw === '') {
                    continue;
                }

                if ($type === 's') {
                    $values[$column] = $shared[(int) $raw] ?? '';
                } else {
                    $values[$column] = $raw;
                }
            }

            $rows[] = $values;
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml);
        $document->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];

        foreach ($document->xpath('//m:si') as $item) {
            $item->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $text = '';

            foreach ($item->xpath('.//m:t') as $part) {
                $text .= (string) $part;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    private function sheetPath(ZipArchive $zip, string $name): string
    {
        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $workbook->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relationshipId = null;

        foreach ($workbook->xpath('//m:sheets/m:sheet') as $sheet) {
            if ((string) $sheet['name'] === $name) {
                $relationshipId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            }
        }

        if ($relationshipId === null) {
            throw new RuntimeException('The By District sheet was not found.');
        }

        $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $rels->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');

        foreach ($rels->xpath('//r:Relationship') as $relationship) {
            if ((string) $relationship['Id'] === $relationshipId) {
                return 'xl/'.ltrim((string) $relationship['Target'], '/');
            }
        }

        throw new RuntimeException('The By District sheet path was not found.');
    }
}
