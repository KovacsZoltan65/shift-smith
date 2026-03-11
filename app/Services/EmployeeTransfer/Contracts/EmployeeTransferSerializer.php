<?php

declare(strict_types=1);

namespace App\Services\EmployeeTransfer\Contracts;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface EmployeeTransferSerializer
{
    public function format(): string;

    public function extension(): string;

    public function mimeType(): string;

    /**
     * @param array<int, array<string, scalar|null>> $records
     */
    public function downloadResponse(array $records, string $fileName): StreamedResponse;

    /**
     * @return array<int, array{row_number:int, values:array<string, mixed>}>
     */
    public function parse(UploadedFile $file): array;
}
