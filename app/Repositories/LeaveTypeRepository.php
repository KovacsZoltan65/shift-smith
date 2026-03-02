<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\LeaveType;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LeaveTypeRepository implements LeaveTypeRepositoryInterface
{
    public function __construct(
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    public function paginateForCompany(int $companyId, array $filters): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['perPage'] ?? 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $sortBy = (string) ($filters['sortBy'] ?? 'name');
        if (! in_array($sortBy, LeaveType::getSortable(), true)) {
            $sortBy = 'name';
        }

        $sortDir = strtolower((string) ($filters['sortDir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $normalized = [
            'companyId' => $companyId,
            'page' => $page,
            'perPage' => $perPage,
            'q' => $this->normalizeString($filters['q'] ?? null),
            'category' => $this->normalizeString($filters['category'] ?? null),
            'active' => $this->normalizeBool($filters['active'] ?? null),
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ];

        $version = $this->cacheVersionService->get("leave_types:{$companyId}:fetch");
        $key = 'v'.$version.':'.hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

        /** @var LengthAwarePaginator<int, LeaveType> */
        return $this->cacheService->remember(
            tag: "leave_types:{$companyId}",
            key: $key,
            callback: function () use ($companyId, $normalized, $page, $perPage): LengthAwarePaginator {
                return LeaveType::query()
                    ->inCompany($companyId)
                    ->search($normalized['q'])
                    ->when($normalized['category'] !== null, fn ($query) => $query->where('category', $normalized['category']))
                    ->when($normalized['active'] !== null, fn ($query) => $query->where('active', $normalized['active']))
                    ->orderBy($normalized['sortBy'], $normalized['sortDir'])
                    ->orderBy('id')
                    ->paginate($perPage, ['*'], 'page', $page);
            },
            ttl: (int) config('cache.ttl_fetch', 60),
        );
    }

    public function findByIdInCompany(int $id, int $companyId): ?LeaveType
    {
        $version = $this->cacheVersionService->get("leave_types:{$companyId}:show");

        /** @var LeaveType|null $leaveType */
        $leaveType = $this->cacheService->remember(
            tag: "leave_types:{$companyId}",
            key: 'v'.$version.':'.$id,
            callback: static fn (): ?LeaveType => LeaveType::query()->inCompany($companyId)->find($id),
            ttl: (int) config('cache.ttl_fetch', 60),
        );

        return $leaveType;
    }

    public function createForCompany(int $companyId, array $data): LeaveType
    {
        /** @var LeaveType $leaveType */
        $leaveType = LeaveType::query()->create([
            ...$data,
            'company_id' => $companyId,
        ]);

        return $leaveType->refresh();
    }

    public function updateInCompany(int $id, int $companyId, array $data): LeaveType
    {
        $leaveType = $this->findRequired($id, $companyId);
        $leaveType->fill($data);
        $leaveType->save();

        return $leaveType->refresh();
    }

    public function deleteInCompany(int $id, int $companyId): void
    {
        $leaveType = $this->findRequired($id, $companyId);
        $leaveType->delete();
    }

    public function categories(int $companyId): array
    {
        $version = $this->cacheVersionService->get("leave_types:{$companyId}:options");

        /** @var list<string> */
        return $this->cacheService->remember(
            tag: "leave_types:{$companyId}",
            key: 'v'.$version.':categories',
            callback: static function () use ($companyId): array {
                return LeaveType::query()
                    ->inCompany($companyId)
                    ->select('category')
                    ->distinct()
                    ->orderBy('category')
                    ->pluck('category')
                    ->map(static fn ($value): string => (string) $value)
                    ->values()
                    ->all();
            },
            ttl: (int) config('cache.ttl_fetch', 300),
        );
    }

    private function findRequired(int $id, int $companyId): LeaveType
    {
        $leaveType = $this->findByIdInCompany($id, $companyId);

        if ($leaveType instanceof LeaveType) {
            return $leaveType;
        }

        throw new NotFoundHttpException('A szabadsag tipus nem talalhato a kivalasztott company scope-ban.');
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function normalizeBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return match ($normalized) {
                '1', 'true' => true,
                '0', 'false' => false,
                default => null,
            };
        }

        if (is_int($value)) {
            return match ($value) {
                1 => true,
                0 => false,
                default => null,
            };
        }

        return null;
    }
}
