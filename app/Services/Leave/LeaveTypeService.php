<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\LeaveType;
use App\Repositories\LeaveCategoryRepositoryInterface;
use App\Repositories\LeaveTypeRepositoryInterface;
use App\Services\Cache\CacheVersionService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class LeaveTypeService
{
    public function __construct(
        private readonly LeaveCategoryRepositoryInterface $leaveCategoryRepository,
        private readonly LeaveTypeRepositoryInterface $repository,
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    public function fetch(int $companyId, array $filters): array
    {
        $paginator = $this->repository->paginateForCompany($companyId, $filters);
        $categoryFilter = isset($filters['category']) && is_array($filters['category'])
            ? array_values($filters['category'])
            : null;

        return [
            'items' => array_map([$this, 'toArray'], $paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'filters' => [
                'q' => $filters['q'] ?? null,
                'category' => $categoryFilter,
                'active' => $filters['active'] ?? null,
                'sortBy' => $filters['sortBy'] ?? 'name',
                'sortDir' => $filters['sortDir'] ?? 'asc',
                'page' => (int) ($filters['page'] ?? 1),
                'perPage' => (int) ($filters['perPage'] ?? 10),
            ],
            'options' => [
                'categories' => $this->leaveCategoryRepository->listForSelector($companyId, true)
                    ->map(static fn ($category): array => [
                        'code' => (string) $category->code,
                        'name' => (string) $category->name,
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    public function selector(int $companyId, array $filters): array
    {
        return $this->repository->selectorForCompany($companyId, $filters);
    }

    public function show(int $companyId, int $id): array
    {
        $leaveType = $this->repository->findByIdInCompany($id, $companyId);

        if (! $leaveType instanceof LeaveType) {
            abort(404, 'A szabadsag tipus nem talalhato.');
        }

        return $this->toArray($leaveType);
    }

    public function store(int $companyId, array $data): array
    {
        $normalized = $this->normalizeStorePayload($data);
        $normalized['code'] = $this->generateUniqueCode($companyId, $normalized['name']);

        $leaveType = $this->repository->createForCompany($companyId, $normalized);
        $this->invalidateCache($companyId);

        return $this->toArray($leaveType);
    }

    public function update(int $companyId, int $id, array $data): array
    {
        $existing = $this->repository->findByIdInCompany($id, $companyId);

        if (! $existing instanceof LeaveType) {
            abort(404, 'A szabadsag tipus nem talalhato.');
        }

        if (array_key_exists('code', $data) && trim((string) $data['code']) !== $existing->code) {
            throw ValidationException::withMessages([
                'code' => 'A kód nem módosítható.',
            ]);
        }

        $normalized = $this->normalizeUpdatePayload($data, $existing->code);

        $hasChanges = $existing->name !== $normalized['name']
            || $existing->category !== $normalized['category']
            || (bool) $existing->affects_leave_balance !== $normalized['affects_leave_balance']
            || (bool) $existing->requires_approval !== $normalized['requires_approval']
            || (bool) $existing->active !== $normalized['active'];

        if (! $hasChanges) {
            return $this->toArray($existing);
        }

        $leaveType = $this->repository->updateInCompany($id, $companyId, $normalized);
        $this->invalidateCache($companyId);

        return $this->toArray($leaveType);
    }

    public function destroy(int $companyId, int $id): void
    {
        $this->repository->deleteInCompany($id, $companyId);
        $this->invalidateCache($companyId);
    }

    private function normalizeStorePayload(array $data): array
    {
        return [
            'name' => trim((string) $data['name']),
            'category' => trim((string) $data['category']),
            'affects_leave_balance' => (bool) $data['affects_leave_balance'],
            'requires_approval' => (bool) $data['requires_approval'],
            'active' => (bool) $data['active'],
        ];
    }

    private function normalizeUpdatePayload(array $data, string $code): array
    {
        return [
            'code' => $code,
            'name' => trim((string) $data['name']),
            'category' => trim((string) $data['category']),
            'affects_leave_balance' => (bool) $data['affects_leave_balance'],
            'requires_approval' => (bool) $data['requires_approval'],
            'active' => (bool) $data['active'],
        ];
    }

    private function generateUniqueCode(int $companyId, string $name): string
    {
        $slug = Str::slug($name, '_');
        $base = 'lt_'.($slug !== '' ? $slug : 'leave_type');
        $candidate = $base;
        $suffix = 2;

        while ($this->repository->existsByCodeInCompany($companyId, $candidate)) {
            $candidate = "{$base}_{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function invalidateCache(int $companyId): void
    {
        $this->cacheVersionService->bump("leave_types:company:{$companyId}:fetch");
        $this->cacheVersionService->bump("leave_types:company:{$companyId}:show");
        $this->cacheVersionService->bump("leave_types:company:{$companyId}:options");
        $this->cacheVersionService->bump("leave_types:company:{$companyId}:selector");
    }

    private function toArray(LeaveType $leaveType): array
    {
        return [
            'id' => (int) $leaveType->id,
            'company_id' => (int) $leaveType->company_id,
            'code' => (string) $leaveType->code,
            'name' => (string) $leaveType->name,
            'category' => (string) $leaveType->category,
            'affects_leave_balance' => (bool) $leaveType->affects_leave_balance,
            'requires_approval' => (bool) $leaveType->requires_approval,
            'active' => (bool) $leaveType->active,
            'created_at' => $leaveType->created_at?->toJSON(),
            'updated_at' => $leaveType->updated_at?->toJSON(),
            'deleted_at' => $leaveType->deleted_at?->toJSON(),
        ];
    }
}
