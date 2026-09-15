<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class SpreadsheetExporter
{
    /**
     * @param array<int, string> $headers
     * @param iterable<int, array<int, scalar|null>> $rows
     * @param array<string, scalar|null> $metadata
     * @param array<int, int|float> $columnWidths One-based column indexes.
     */
    public static function downloadXlsx(
        string $filename,
        string $sheetTitle,
        array $headers,
        iterable $rows,
        array $metadata = [],
        array $columnWidths = []
    ): void {
        if ($headers === []) {
            throw new InvalidArgumentException('At least one spreadsheet header is required.');
        }

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle(self::normaliseSheetTitle($sheetTitle));

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $currentRow = 1;

        if ($sheetTitle !== '') {
            $worksheet->setCellValueExplicit('A1', $sheetTitle, DataType::TYPE_STRING);
            $worksheet->mergeCells("A1:{$lastColumn}1");
            $worksheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $worksheet->getRowDimension(1)->setRowHeight(26);
            $currentRow = 3;
        }

        foreach ($metadata as $label => $value) {
            $worksheet->setCellValueExplicit("A{$currentRow}", (string) $label, DataType::TYPE_STRING);
            $worksheet->setCellValueExplicit("B{$currentRow}", self::stringValue($value), DataType::TYPE_STRING);
            $worksheet->getStyle("A{$currentRow}")->getFont()->setBold(true);
            ++$currentRow;
        }

        if ($metadata !== []) {
            ++$currentRow;
        }

        $headerRow = $currentRow;
        foreach (array_values($headers) as $index => $header) {
            $cell = Coordinate::stringFromColumnIndex($index + 1) . $headerRow;
            $worksheet->setCellValueExplicit($cell, (string) $header, DataType::TYPE_STRING);
        }

        $worksheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2F3']],
            ],
        ]);
        $worksheet->getRowDimension($headerRow)->setRowHeight(32);

        $dataRow = $headerRow + 1;
        foreach ($rows as $row) {
            foreach (array_values($row) as $index => $value) {
                if ($index >= count($headers)) {
                    break;
                }

                $cell = Coordinate::stringFromColumnIndex($index + 1) . $dataRow;
                $worksheet->setCellValueExplicit($cell, self::stringValue($value), DataType::TYPE_STRING);
            }
            ++$dataRow;
        }

        $lastDataRow = max($headerRow, $dataRow - 1);
        if ($lastDataRow > $headerRow) {
            $worksheet->getStyle("A" . ($headerRow + 1) . ":{$lastColumn}{$lastDataRow}")
                ->getAlignment()
                ->setVertical(Alignment::VERTICAL_TOP)
                ->setWrapText(true);
        }
        $worksheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$lastDataRow}");
        $worksheet->freezePane('A' . ($headerRow + 1));

        foreach ($headers as $index => $_header) {
            $columnIndex = $index + 1;
            $width = $columnWidths[$columnIndex] ?? 30;
            $worksheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))
                ->setWidth((float) $width);
        }

        $filename = self::normaliseFilename($filename);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($temporaryFile === false) {
            $spreadsheet->disconnectWorksheets();
            throw new RuntimeException('Unable to create a temporary spreadsheet file.');
        }

        try {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save($temporaryFile);

            if (headers_sent($sourceFile, $sourceLine)) {
                throw new RuntimeException("Response headers were already sent by {$sourceFile}:{$sourceLine}.");
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . (string) filesize($temporaryFile));
            header('Cache-Control: max-age=0, no-store, no-cache, must-revalidate');
            header('Pragma: public');

            if (readfile($temporaryFile) === false) {
                throw new RuntimeException('Unable to send the spreadsheet file.');
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }

        exit;
    }

    private static function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private static function normaliseFilename(string $filename): string
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?? '';
        $filename = trim($filename, '._-');

        if ($filename === '') {
            $filename = 'export.xlsx';
        } elseif (!str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        return $filename;
    }

    private static function normaliseSheetTitle(string $title): string
    {
        $title = preg_replace('/[\\\\\/?*\[\]:]/u', ' ', trim($title)) ?? '';
        $title = trim($title, " '");
        if ($title === '') {
            return 'Export';
        }

        return mb_substr($title, 0, 31, 'UTF-8');
    }
}
