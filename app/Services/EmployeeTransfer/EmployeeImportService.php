<?php

declare(strict_types=1);

namespace App\Services\EmployeeTransfer;

use App\Interfaces\EmployeeRepositoryInterface;
use App\Interfaces\PositionRepositoryInterface;
use App\Models\Employee;
use App\Services\Cache\CacheVersionService;
use App\Services\Org\PositionOrgLevelService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class EmployeeImportService
{
    private const NS_EMPLOYEES_FETCH = 'employees.fetch';
    private const NS_SELECTORS_EMPLOYEES = 'selectors.employees';
    private const NS_SELECTORS_COMPANIES = 'selectors.companies';
    private const NS_DASHBOARD_STATS = 'dashboard.stats';

    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly PositionRepositoryInterface $positionRepository,
        private readonly PositionOrgLevelService $positionOrgLevelService,
        private readonly EmployeeTransferSerializerRegistry $serializers,
        private readonly CacheVersionService $cacheVersionService,
    ) {}

    /**
     * @return array{
     *   total_rows:int,
     *   imported_count:int,
     *   failed_count:int,
     *   skipped_count:int,
     *   rows: array<int, array{row_number:int,status:string,message:string,errors:array<int,string>}>
     * }
     */
    public function import(int $companyId, string $format, UploadedFile $file): array
    {
        $parsedRows = $this->serializers->for($format)->parse($file);

        $result = [
            'total_rows' => count($parsedRows),
            'imported_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'rows' => [],
        ];

        foreach ($parsedRows as $parsedRow) {
            $rowNumber = (int) $parsedRow['row_number'];
            $values = $this->normalizeValues($parsedRow['values']);

            if ($this->isBlankRow($values)) {
                $result['skipped_count']++;
                $result['rows'][] = [
                    'row_number' => $rowNumber,
                    'status' => 'skipped',
                    'message' => __('employees.import.rows.skipped_blank'),
                    'errors' => [],
                ];

                continue;
            }

            $validation = $this->validateRow($companyId, $values);

            if ($validation['errors'] !== []) {
                $result['failed_count']++;
                $result['rows'][] = [
                    'row_number' => $rowNumber,
                    'status' => 'failed',
                    'message' => $validation['message'],
                    'errors' => $validation['errors'],
                ];

                continue;
            }

            DB::transaction(function () use ($companyId, $validation): void {
                $positionId = $validation['position_id'];

                $this->employeeRepository->storeForImport([
                    'company_id' => $companyId,
                    'first_name' => $validation['data']['first_name'],
                    'last_name' => $validation['data']['last_name'],
                    'email' => $validation['data']['email'],
                    'address' => $validation['data']['address'],
                    'position_id' => $positionId,
                    'org_level' => $this->resolveOrgLevel($companyId, $positionId),
                    'phone' => $validation['data']['phone'],
                    'birth_date' => $validation['data']['birth_date'],
                    'hired_at' => $validation['data']['hired_at'],
                    'active' => $validation['data']['active'],
                ]);
            });

            $result['imported_count']++;
            $result['rows'][] = [
                'row_number' => $rowNumber,
                'status' => 'imported',
                'message' => __('employees.import.rows.imported'),
                'errors' => [],
            ];
        }

        if ($result['imported_count'] > 0) {
            $this->bumpEmployeeCaches();
        }

        return $result;
    }

    /**
     * @param array<string, string|null> $values
     * @return array{
     *   message:string,
     *   errors:array<int,string>,
     *   position_id:int|null,
     *   data:array{
     *     first_name:string,
     *     last_name:string,
     *     email:string,
     *     phone:string|null,
     *     address:string|null,
     *     birth_date:string,
     *     hired_at:string|null,
     *     active:bool
     *   }
     * }
     */
    private function validateRow(int $companyId, array $values): array
    {
        $active = $this->parseBooleanLike($values['active']);

        if ($active === null && $values['active'] !== null && $values['active'] !== '') {
            return [
                'message' => __('employees.import.rows.validation_failed'),
                'errors' => [__('employees.import.errors.invalid_active')],
                'position_id' => null,
                'data' => $this->emptyData(),
            ];
        }

        $payload = [
            'first_name' => $values['first_name'],
            'last_name' => $values['last_name'],
            'email' => $values['email'] !== null ? mb_strtolower($values['email'], 'UTF-8') : null,
            'phone' => $values['phone'],
            'address' => $values['address'],
            'position_name' => $values['position_name'],
            'birth_date' => $values['birth_date'],
            'hired_at' => $values['hired_at'],
            'active' => $active ?? true,
        ];

        $validator = Validator::make($payload, [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'position_name' => ['nullable', 'string', 'max:120'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before:today'],
            'hired_at' => ['nullable', 'date_format:Y-m-d'],
            'active' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return [
                'message' => __('employees.import.rows.validation_failed'),
                'errors' => $validator->errors()->all(),
                'position_id' => null,
                'data' => $this->emptyData(),
            ];
        }

        $positionId = null;
        $positionName = trim((string) ($payload['position_name'] ?? ''));

        if ($positionName !== '') {
            $position = $this->positionRepository->findByNameInCompany($companyId, $positionName);

            if ($position === null) {
                return [
                    'message' => __('employees.import.rows.position_not_found'),
                    'errors' => [__('employees.import.errors.position_not_found', ['position' => $positionName])],
                    'position_id' => null,
                    'data' => $this->emptyData(),
                ];
            }

            $positionId = (int) $position->id;
        }

        if ($this->employeeRepository->findActiveByEmail($companyId, (string) $payload['email']) instanceof Employee) {
            return [
                'message' => __('employees.import.rows.duplicate_email'),
                'errors' => [__('employees.messages.active_email_exists')],
                'position_id' => null,
                'data' => $this->emptyData(),
            ];
        }

        if ($this->employeeRepository->findSoftDeletedByEmail($companyId, (string) $payload['email']) instanceof Employee) {
            return [
                'message' => __('employees.import.rows.restore_available'),
                'errors' => ['restore_available'],
                'position_id' => null,
                'data' => $this->emptyData(),
            ];
        }

        return [
            'message' => __('employees.import.rows.imported'),
            'errors' => [],
            'position_id' => $positionId,
            'data' => [
                'first_name' => trim((string) $payload['first_name']),
                'last_name' => trim((string) $payload['last_name']),
                'email' => trim((string) $payload['email']),
                'phone' => $payload['phone'] !== null && $payload['phone'] !== '' ? trim((string) $payload['phone']) : null,
                'address' => $payload['address'] !== null && $payload['address'] !== '' ? trim((string) $payload['address']) : null,
                'birth_date' => (string) $payload['birth_date'],
                'hired_at' => $payload['hired_at'] !== null && $payload['hired_at'] !== '' ? (string) $payload['hired_at'] : null,
                'active' => (bool) $payload['active'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string|null>
     */
    private function normalizeValues(array $values): array
    {
        $normalized = [];

        foreach (EmployeeTransferFormat::FIELDS as $field) {
            $value = $values[$field] ?? null;
            $normalized[$field] = is_string($value)
                ? trim($value)
                : ($value === null ? null : trim((string) $value));
        }

        return $normalized;
    }

    /**
     * @param array<string, string|null> $values
     */
    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseBooleanLike(?string $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match (mb_strtolower(trim($value), 'UTF-8')) {
            '1', 'true', 'yes', 'igen' => true,
            '0', 'false', 'no', 'nem' => false,
            default => null,
        };
    }

    private function resolveOrgLevel(int $companyId, ?int $positionId): string
    {
        if (! is_int($positionId) || $positionId <= 0) {
            return Employee::ORG_LEVEL_STAFF;
        }

        $position = $this->positionRepository->getPosition($positionId, $companyId);

        return $this->positionOrgLevelService->resolveOrgLevel($companyId, (string) $position->name);
    }

    /**
     * @return array{
     *   first_name:string,
     *   last_name:string,
     *   email:string,
     *   phone:null,
     *   address:null,
     *   birth_date:string,
     *   hired_at:null,
     *   active:bool
     * }
     */
    private function emptyData(): array
    {
        return [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => null,
            'address' => null,
            'birth_date' => '',
            'hired_at' => null,
            'active' => true,
        ];
    }

    private function bumpEmployeeCaches(): void
    {
        $this->cacheVersionService->bump(self::NS_EMPLOYEES_FETCH);
        $this->cacheVersionService->bump(self::NS_SELECTORS_EMPLOYEES);
        $this->cacheVersionService->bump(self::NS_SELECTORS_COMPANIES);
        $this->cacheVersionService->bump(self::NS_DASHBOARD_STATS);
    }
}
