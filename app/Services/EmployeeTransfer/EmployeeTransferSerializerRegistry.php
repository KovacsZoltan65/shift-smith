<?php

declare(strict_types=1);

namespace App\Services\EmployeeTransfer;

use App\Services\EmployeeTransfer\Contracts\EmployeeTransferSerializer;
use App\Services\EmployeeTransfer\Formats\CsvEmployeeTransferSerializer;
use App\Services\EmployeeTransfer\Formats\JsonEmployeeTransferSerializer;
use App\Services\EmployeeTransfer\Formats\XmlEmployeeTransferSerializer;
use App\Services\EmployeeTransfer\Formats\XlsxEmployeeTransferSerializer;
use InvalidArgumentException;

final class EmployeeTransferSerializerRegistry
{
    public function __construct(
        private readonly CsvEmployeeTransferSerializer $csv,
        private readonly JsonEmployeeTransferSerializer $json,
        private readonly XmlEmployeeTransferSerializer $xml,
        private readonly XlsxEmployeeTransferSerializer $xlsx,
    ) {}

    public function for(string $format): EmployeeTransferSerializer
    {
        return match (EmployeeTransferFormat::normalize($format)) {
            EmployeeTransferFormat::CSV => $this->csv,
            EmployeeTransferFormat::JSON => $this->json,
            EmployeeTransferFormat::XML => $this->xml,
            EmployeeTransferFormat::XLSX => $this->xlsx,
            default => throw new InvalidArgumentException('Unsupported employee transfer format.'),
        };
    }
}
