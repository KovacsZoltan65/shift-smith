<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\SickLeaveCategory;
use App\Repositories\SickLeaveCategoryRepositoryInterface;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SickLeaveCategoryService
{
    public function __construct(
        private readonly SickLeaveCategoryRepositoryInterface $repository,
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
                'active' => $filters['active'] ?? null,
                'sortBy' => $filters['sortBy'] ?? 'order_index',
                'sortDir' => $filters['sortDir'] ?? 'asc',
                'page' => (int) ($filters['page'] ?? 1),
                'perPage' => (int) ($filters['perPage'] ?? 10),
            ],
        ];
    }

    /**
     * @return list<array{id:int,name:string,code:string,active:bool}>
     */
    public function selector(int $companyId, bool $onlyActive = true): array
    {
        return $this->repository->listForSelector($companyId, $onlyActive)
            ->map(static fn (SickLeaveCategory $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'code' => (string) $category->code,
                'active' => (bool) $category->active,
            ])
            ->values()
            ->all();
    }

    public function show(int $companyId, int $id): array
    {
        $category = $this->repository->findByIdInCompany($id, $companyId);

        if (! $category instanceof SickLeaveCategory) {
            abort(404, 'A betegszabadsag kategoria nem talalhato.');
        }

        return $this->toArray($category);
    }

    public function store(int $companyId, array $data): array
    {
        $normalized = $this->normalizeStorePayload($data);
        $normalized['code'] = $this->generateUniqueCode($companyId, $normalized['name']);

        $category = $this->repository->createForCompany($companyId, $normalized);
        $this->invalidateCache($companyId);

        return $this->toArray($category);
    }

    public function update(int $companyId, int $id, array $data): array
    {
        $existing = $this->repository->findByIdInCompany($id, $companyId);

        if (! $existing instanceof SickLeaveCategory) {
            abort(404, 'A betegszabadsag kategoria nem talalhato.');
        }

        if (array_key_exists('code', $data) && trim((string) $data['code']) !== $existing->code) {
            throw ValidationException::withMessages([
                'code' => 'A kód nem módosítható.',
            ]);
        }

        $normalized = $this->normalizeUpdatePayload($data, $existing->code);
        $category = $this->repository->updateInCompany($id, $companyId, $normalized);
        $this->invalidateCache($companyId);

        return $this->toArray($category);
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
            'description' => $this->normalizeDescription($data['description'] ?? null),
            'active' => (bool) $data['active'],
            'order_index' => max(0, (int) ($data['order_index'] ?? 0)),
        ];
    }

    private function normalizeUpdatePayload(array $data, string $code): array
    {
        return [
            'code' => $code,
            'name' => trim((string) $data['name']),
            'description' => $this->normalizeDescription($data['description'] ?? null),
            'active' => (bool) $data['active'],
            'order_index' => max(0, (int) ($data['order_index'] ?? 0)),
        ];
    }

    private function normalizeDescription(mixed $description): ?string
    {
        if (! is_string($description)) {
            return null;
        }

        $normalized = trim($description);

        return $normalized === '' ? null : $normalized;
    }

    private function generateUniqueCode(int $companyId, string $name): string
    {
        $slug = Str::slug($name, '_');
        $base = $slug !== '' ? $slug : 'sick_leave_category';
        $candidate = $base;
        $suffix = 2;

        while ($this->repository->existsCodeInCompany($companyId, $candidate)) {
            $candidate = "{$base}_{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function invalidateCache(int $companyId): void
    {
        $this->cacheVersionService->bump("sick_leave_categories:company:{$companyId}:fetch");
        $this->cacheVersionService->bump("sick_leave_categories:company:{$companyId}:show");
        $this->cacheVersionService->bump("sick_leave_categories:company:{$companyId}:selector");
    }

    private function toArray(SickLeaveCategory $category): array
    {
        return [
            'id' => (int) $category->id,
            'company_id' => (int) $category->company_id,
            'code' => (string) $category->code,
            'name' => (string) $category->name,
            'description' => $category->description,
            'active' => (bool) $category->active,
            'order_index' => (int) $category->order_index,
            'created_at' => $category->created_at?->toJSON(),
            'updated_at' => $category->updated_at?->toJSON(),
            'deleted_at' => $category->deleted_at?->toJSON(),
        ];
    }
}
