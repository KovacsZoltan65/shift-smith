<?php

declare(strict_types=1);

namespace App\Services\EmployeeTransfer;

use App\Interfaces\PositionRepositoryInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EmployeeTemplateService
{
    public function __construct(
        private readonly PositionRepositoryInterface $positionRepository,
        private readonly EmployeeTransferSerializerRegistry $serializers,
    ) {}

    public function download(int $companyId, string $format): StreamedResponse
    {
        $serializer = $this->serializers->for($format);

        return $serializer->downloadResponse([
            [
                'last_name' => 'Doe',
                'first_name' => 'Jane',
                'email' => 'jane.doe@example.test',
                'phone' => '+36 30 123 4567',
                'address' => '1011 Budapest, Fő utca 1.',
                'position_name' => $this->positionRepository->findFirstActiveNameInCompany($companyId),
                'birth_date' => '1990-01-01',
                'hired_at' => now()->toDateString(),
                'active' => 'true',
            ],
        ], 'employees-template-'.now()->toDateString().'.'.$serializer->extension());
    }
}
