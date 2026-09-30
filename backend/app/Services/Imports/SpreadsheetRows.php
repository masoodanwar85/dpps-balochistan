<?php

namespace App\Services\Imports;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class SpreadsheetRows
{
    /**
     * @return array{headers: array<int, string>, rows: list<array{row: int, values: array<string, string>}>}
     */
    public function read(string $path, string $title): array
    {
        $sheet = IOFactory::load($path)->getSheetByName($title);

        if (! $sheet instanceof Worksheet) {
            throw new RuntimeException('The workbook has no "'.$title.'" sheet.');
        }

        $lastRow = $sheet->getHighestDataRow();
        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $headerRow = null;
        $headers = [];

        for ($row = 1; $row <= min(5, $lastRow); $row++) {
            $labels = $this->labels($sheet, $row, $lastColumn);

            if ($this->isHeader($title, $labels)) {
                $headerRow = $row;
                $headers = $labels;
                break;
            }
        }

        if ($headerRow === null) {
            throw new RuntimeException('The "'.$title.'" sheet has no header row.');
        }

        if ($title === 'By District') {
            $headers = $this->dealerHeaders($sheet, $headerRow, $lastColumn, $headers);
        }

        $keys = $this->keys($headers);
        $rows = [];

        for ($row = $headerRow + 1; $row <= $lastRow; $row++) {
            $values = [];
            $filled = false;

            foreach ($keys as $column => $key) {
                $text = $this->text($sheet->getCell([$column, $row]), $key === 'name of products');
                $values[$key] = $text;
                $filled = $filled || $text !== '';
            }

            if ($filled) {
                $rows[] = ['row' => $row, 'values' => $values];
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array<int, string>
     */
    private function labels(Worksheet $sheet, int $row, int $lastColumn): array
    {
        $labels = [];

        for ($column = 1; $column <= $lastColumn; $column++) {
            $text = $this->text($sheet->getCell([$column, $row]), false);

            if ($text !== '') {
                $labels[$column] = $text;
            }
        }

        return $labels;
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function isHeader(string $title, array $labels): bool
    {
        $normalized = array_map(fn (string $label) => mb_strtolower($label), $labels);

        if ($title === 'By District') {
            return in_array('district', $normalized, true) && in_array('buisness address', $normalized, true);
        }

        if ($title === 'Company Details') {
            return in_array('name', $normalized, true) && in_array('licenses exp', $normalized, true);
        }

        return in_array('name of company', $normalized, true);
    }

    /**
     * Day, month and year are ignored. A blank header after the registration
     * columns is the fee amount.
     *
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    private function dealerHeaders(Worksheet $sheet, int $headerRow, int $lastColumn, array $headers): array
    {
        for ($column = 1; $column <= $lastColumn; $column++) {
            $below = mb_strtolower($this->text($sheet->getCell([$column, $headerRow + 1]), false));

            if (in_array($below, ['day', 'month', 'year'], true)) {
                unset($headers[$column]);

                continue;
            }

            if (! isset($headers[$column])) {
                $headers[$column] = 'fee';
            }
        }

        ksort($headers);

        return $headers;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    private function keys(array $headers): array
    {
        $seen = [];
        $keys = [];

        foreach ($headers as $column => $label) {
            $key = mb_strtolower(trim($label));
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $keys[$column] = $seen[$key] === 1 ? $key : $key.' '.$seen[$key];
        }

        return $keys;
    }

    private function text(Cell $cell, bool $keepBreaks): string
    {
        $value = $cell->getValue();

        if (is_numeric($value) && ExcelDate::isDateTime($cell)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('j-n-Y');
        }

        $text = trim((string) $value);

        if (! $keepBreaks) {
            $text = trim(str_replace(["\r\n", "\n", "\r"], ' ', $text));
        }

        return $text;
    }
}
