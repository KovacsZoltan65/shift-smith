<?php

declare(strict_types=1);

namespace App\Services\EmployeeTransfer\Formats;

use App\Services\EmployeeTransfer\Contracts\EmployeeTransferSerializer;
use App\Services\EmployeeTransfer\EmployeeTransferFormat;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class XlsxEmployeeTransferSerializer implements EmployeeTransferSerializer
{
    public function format(): string
    {
        return EmployeeTransferFormat::XLSX;
    }

    public function extension(): string
    {
        return 'xlsx';
    }

    public function mimeType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function downloadResponse(array $records, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($records): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            foreach (EmployeeTransferFormat::FIELDS as $columnIndex => $field) {
                $sheet->setCellValue([$columnIndex + 1, 1], $field);
            }

            foreach (array_values($records) as $rowIndex => $record) {
                foreach (EmployeeTransferFormat::FIELDS as $columnIndex => $field) {
                    $sheet->setCellValue([$columnIndex + 1, $rowIndex + 2], $record[$field] ?? '');
                }
            }

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => $this->mimeType(),
        ]);
    }

    public function parse(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath() ?: $file->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if ($rows === []) {
            $spreadsheet->disconnectWorksheets();

            return [];
        }

        $header = array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            array_values($rows[0] ?? [])
        );

        $parsedRows = [];

        foreach (array_slice($rows, 1, null, true) as $index => $row) {
            $values = [];

            foreach (EmployeeTransferFormat::FIELDS as $field) {
                $columnIndex = array_search($field, $header, true);
                $values[$field] = $columnIndex === false ? null : ($row[$columnIndex] ?? null);
            }

            $parsedRows[] = [
                'row_number' => $index + 1,
                'values' => $values,
            ];
        }

        $spreadsheet->disconnectWorksheets();

        return $parsedRows;
    }
}
