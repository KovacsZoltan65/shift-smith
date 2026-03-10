<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\LeaveCategory;
use App\Repositories\LeaveCategoryRepositoryInterface;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeaveCategoryService
{
    public function __construct(
        private readonly LeaveCategoryRepositoryInterface $repository,
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
     * @return list<array{code:string,name:string,active:bool}>
     */
    public function selector(int $companyId, bool $onlyActive = true): array
    {
        return $this->repository->listForSelector($companyId, $onlyActive)
            ->map(static fn (LeaveCategory $category): array => [
                'id' => (int) $category->id,
                'code' => (string) $category->code,
                'name' => (string) $category->name,
                'active' => (bool) $category->active,
            ])
            ->values()
            ->all();
    }

    public function show(int $companyId, int $id): array
    {
        $category = $this->repository->findByIdInCompany($id, $companyId);

        if (! $category instanceof LeaveCategory) {
            abort(404, __('leave_categories.errors.not_found'));
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

        if (! $existing instanceof LeaveCategory) {
            abort(404, __('leave_categories.errors.not_found'));
        }

        if (array_key_exists('code', $data) && trim((string) $data['code']) !== $existing->code) {
            throw ValidationException::withMessages([
                'code' => __('leave_categories.validation.code_immutable'),
            ]);
        }

        $category = $this->repository->updateInCompany($id, $companyId, $this->normalizeUpdatePayload($data, $existing->code));
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
        $base = $slug !== '' ? $slug : 'leave_category';
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
        $this->cacheVersionService->bump("leave_categories:company:{$companyId}:fetch");
        $this->cacheVersionService->bump("leave_categories:company:{$companyId}:show");
        $this->cacheVersionService->bump("leave_categories:company:{$companyId}:selector");
    }

    private function toArray(LeaveCategory $category): array
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
