<?php

declare(strict_types=1);

namespace App\Services\EmployeeTransfer\Formats;

use App\Services\EmployeeTransfer\Contracts\EmployeeTransferSerializer;
use App\Services\EmployeeTransfer\EmployeeTransferFormat;
use Illuminate\Http\UploadedFile;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class XmlEmployeeTransferSerializer implements EmployeeTransferSerializer
{
    public function format(): string
    {
        return EmployeeTransferFormat::XML;
    }

    public function extension(): string
    {
        return 'xml';
    }

    public function mimeType(): string
    {
        return 'application/xml';
    }

    public function downloadResponse(array $records, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($records): void {
            $root = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><employees />');

            foreach ($records as $record) {
                $employeeNode = $root->addChild('employee');

                foreach (EmployeeTransferFormat::FIELDS as $field) {
                    $employeeNode->addChild($field, htmlspecialchars((string) ($record[$field] ?? '')));
                }
            }

            echo $root->asXML();
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

        $xml = @simplexml_load_string($content);

        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $rows = [];

        foreach ($xml->employee ?? [] as $index => $employeeNode) {
            $values = [];

            foreach (EmployeeTransferFormat::FIELDS as $field) {
                $values[$field] = isset($employeeNode->{$field}) ? (string) $employeeNode->{$field} : null;
            }

            $rows[] = [
                'row_number' => (int) $index + 1,
                'values' => $values,
            ];
        }

        return $rows;
    }
}
