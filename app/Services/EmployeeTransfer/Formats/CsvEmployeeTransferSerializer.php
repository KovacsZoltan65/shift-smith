<?php

declare(strict_types=1);

namespace App\Services\EmployeeTransfer\Formats;

use App\Services\EmployeeTransfer\Contracts\EmployeeTransferSerializer;
use App\Services\EmployeeTransfer\EmployeeTransferFormat;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvEmployeeTransferSerializer implements EmployeeTransferSerializer
{
    public function format(): string
    {
        return EmployeeTransferFormat::CSV;
    }

    public function extension(): string
    {
        return 'csv';
    }

    public function mimeType(): string
    {
        return 'text/csv';
    }

    public function downloadResponse(array $records, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($records): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, EmployeeTransferFormat::FIELDS);

            foreach ($records as $record) {
                $row = [];

                foreach (EmployeeTransferFormat::FIELDS as $field) {
                    $row[] = $record[$field] ?? null;
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => $this->mimeType(),
        ]);
    }

    public function parse(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath() ?: $file->getPathname(), 'rb');

        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle);

        if (! is_array($header)) {
            fclose($handle);

            return [];
        }

        $normalizedHeader = array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            $header
        );

        $rows = [];
        $rowNumber = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $values = [];

            foreach (EmployeeTransferFormat::FIELDS as $field) {
                $index = array_search($field, $normalizedHeader, true);
                $values[$field] = $index === false ? null : ($data[$index] ?? null);
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'values' => $values,
            ];
        }

        fclose($handle);

        return $rows;
    }
}
