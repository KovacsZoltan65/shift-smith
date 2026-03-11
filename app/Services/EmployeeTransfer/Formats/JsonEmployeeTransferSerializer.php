<?php

declare(strict_types=1);

namespace App\Services\EmployeeTransfer\Formats;

use App\Services\EmployeeTransfer\Contracts\EmployeeTransferSerializer;
use App\Services\EmployeeTransfer\EmployeeTransferFormat;
use Illuminate\Http\UploadedFile;
use JsonException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class JsonEmployeeTransferSerializer implements EmployeeTransferSerializer
{
    public function format(): string
    {
        return EmployeeTransferFormat::JSON;
    }

    public function extension(): string
    {
        return 'json';
    }

    public function mimeType(): string
    {
        return 'application/json';
    }

    public function downloadResponse(array $records, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($records): void {
            echo json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }, $fileName, [
            'Content-Type' => $this->mimeType(),
        ]);
    }

    public function parse(UploadedFile $file): array
    {
        $content = $file->get();

        if ($content === false || trim($content) === '') {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $rows = [];

        foreach (array_values($decoded) as $index => $item) {
            $source = is_array($item) ? $item : [];
            $values = [];

            foreach (EmployeeTransferFormat::FIELDS as $field) {
                $values[$field] = $source[$field] ?? null;
            }

            $rows[] = [
                'row_number' => $index + 1,
                'values' => $values,
            ];
        }

        return $rows;
    }
}
