<?php

namespace App\Services\DataImport;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class ExcelInspectorService
{
    /**
     * Inspect an uploaded Excel workbook and return sheets with approximate row counts.
     */
    public function inspectWorkbook(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Uploaded file does not exist: {$filePath}");
        }

        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $sheetNames = $reader->listWorksheetNames($filePath);

        $sheets = [];
        foreach ($sheetNames as $name) {
            $info = $reader->listWorksheetInfo($filePath);
            $rowCount = 0;
            foreach ($info as $sheetInfo) {
                if ($sheetInfo['worksheetName'] === $name) {
                    $rowCount = $sheetInfo['totalRows'];
                    break;
                }
            }

            $sheets[] = [
                'name' => $name,
                'approximate_rows' => $rowCount,
            ];
        }

        return $sheets;
    }

    /**
     * Read sample rows from a specific sheet to preview data and auto-detect header row.
     */
    public function getPreview(string $filePath, string $sheetName, int $headerRow = 1, int $sampleLimit = 20): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly($sheetName);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $highestRow = $worksheet->getHighestRow();
        $highestCol = $worksheet->getHighestColumn();

        // Detect or extract headers
        $headers = [];
        $headerRowData = $worksheet->rangeToArray("A{$headerRow}:{$highestCol}{$headerRow}", null, true, true, true)[$headerRow] ?? [];
        foreach ($headerRowData as $col => $val) {
            $headers[$col] = trim((string) $val);
        }

        // Preview sample rows
        $rows = [];
        $startRow = $headerRow + 1;
        $endRow = min($highestRow, $startRow + $sampleLimit - 1);

        if ($startRow <= $highestRow) {
            $data = $worksheet->rangeToArray("A{$startRow}:{$highestCol}{$endRow}", null, true, true, true);
            foreach ($data as $rNum => $row) {
                $rowItem = [];
                foreach ($headers as $col => $headerName) {
                    $rowItem[$col] = $row[$col] ?? null;
                }
                $rows[] = [
                    'row_number' => $rNum,
                    'values' => $rowItem,
                ];
            }
        }

        return [
            'sheet_name' => $sheetName,
            'header_row' => $headerRow,
            'headers' => $headers,
            'total_rows' => max(0, $highestRow - $headerRow),
            'sample_rows' => $rows,
        ];
    }
}
