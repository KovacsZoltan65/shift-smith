<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\LeaveType;
use App\Repositories\LeaveTypeRepositoryInterface;
use App\Services\Cache\CacheVersionService;

class LeaveTypeService
{
    public function __construct(
        private readonly LeaveTypeRepositoryInterface $repository,
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    public function fetch(int $companyId, array $filters): array
    {
        $paginator = $this->repository->paginateForCompany($companyId, $filters);

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
                'category' => $filters['category'] ?? null,
                'active' => $filters['active'] ?? null,
                'sortBy' => $filters['sortBy'] ?? 'name',
                'sortDir' => $filters['sortDir'] ?? 'asc',
                'page' => (int) ($filters['page'] ?? 1),
                'perPage' => (int) ($filters['perPage'] ?? 10),
            ],
            'options' => [
                'categories' => $this->repository->categories($companyId),
            ],
        ];
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
        $leaveType = $this->repository->createForCompany($companyId, $this->normalizePayload($data));
        $this->invalidateCache($companyId);

        return $this->toArray($leaveType);
    }

    public function update(int $companyId, int $id, array $data): array
    {
        $leaveType = $this->repository->updateInCompany($id, $companyId, $this->normalizePayload($data));
        $this->invalidateCache($companyId);

        return $this->toArray($leaveType);
    }

    public function destroy(int $companyId, int $id): void
    {
        $this->repository->deleteInCompany($id, $companyId);
        $this->invalidateCache($companyId);
    }

    private function normalizePayload(array $data): array
    {
        return [
            'code' => trim((string) $data['code']),
            'name' => trim((string) $data['name']),
            'category' => trim((string) $data['category']),
            'affects_leave_balance' => (bool) $data['affects_leave_balance'],
            'requires_approval' => (bool) $data['requires_approval'],
            'active' => (bool) $data['active'],
        ];
    }

    private function invalidateCache(int $companyId): void
    {
        $this->cacheVersionService->bump("leave_types:{$companyId}:fetch");
        $this->cacheVersionService->bump("leave_types:{$companyId}:show");
        $this->cacheVersionService->bump("leave_types:{$companyId}:options");
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
