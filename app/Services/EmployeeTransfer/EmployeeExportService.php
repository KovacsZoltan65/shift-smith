<?php

declare(strict_types=1);

namespace App\Services\EmployeeTransfer;

use App\Interfaces\EmployeeRepositoryInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EmployeeExportService
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly EmployeeTransferSerializerRegistry $serializers,
    ) {}

    public function export(int $companyId, string $format): StreamedResponse
    {
        $serializer = $this->serializers->for($format);

        return $serializer->downloadResponse(
            $this->employeeRepository->portableExportRows($companyId),
            'employees-export-'.now()->toDateString().'.'.$serializer->extension(),
        );
    }
}
