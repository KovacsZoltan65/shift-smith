<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\LeaveCategory;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LeaveCategoryRepository implements LeaveCategoryRepositoryInterface
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

        $sortBy = (string) ($filters['sortBy'] ?? 'order_index');
        if (! in_array($sortBy, LeaveCategory::getSortable(), true)) {
            $sortBy = 'order_index';
        }

        $sortDir = strtolower((string) ($filters['sortDir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $normalized = [
            'companyId' => $companyId,
            'page' => $page,
            'perPage' => $perPage,
            'q' => $this->normalizeString($filters['q'] ?? null),
            'active' => $this->normalizeBool($filters['active'] ?? null),
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ];

        $version = $this->cacheVersionService->get($this->versionNamespace($companyId, 'fetch'));
        $key = 'v'.$version.':'.hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

        /** @var LengthAwarePaginator<int, LeaveCategory> */
        return $this->cacheService->remember(
            tag: $this->cacheTag($companyId),
            key: $key,
            callback: function () use ($companyId, $normalized, $page, $perPage): LengthAwarePaginator {
                return LeaveCategory::query()
                    ->inCompany($companyId)
                    ->search($normalized['q'])
                    ->when($normalized['active'] !== null, fn ($query) => $query->where('active', $normalized['active']))
                    ->orderBy($normalized['sortBy'], $normalized['sortDir'])
                    ->orderBy('name')
                    ->orderBy('id')
                    ->paginate($perPage, ['*'], 'page', $page);
            },
            ttl: (int) config('cache.ttl_fetch', 60),
        );
    }

    public function listActiveForCompany(int $companyId): Collection
    {
        return LeaveCategory::query()
            ->inCompany($companyId)
            ->where('active', true)
            ->orderBy('order_index')
            ->orderBy('name')
            ->get();
    }

    public function listForSelector(int $companyId, bool $onlyActive = true): Collection
    {
        $normalized = [
            'companyId' => $companyId,
            'onlyActive' => $onlyActive,
        ];

        $version = $this->cacheVersionService->get($this->versionNamespace($companyId, 'selector'));
        $key = 'v'.$version.':'.hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

        /** @var Collection<int, LeaveCategory> */
        return $this->cacheService->remember(
            tag: $this->cacheTag($companyId),
            key: $key,
            callback: static function () use ($companyId, $onlyActive): Collection {
                return LeaveCategory::query()
                    ->inCompany($companyId)
                    ->when($onlyActive, fn ($query) => $query->where('active', true))
                    ->orderBy('order_index')
                    ->orderBy('name')
                    ->get(['id', 'company_id', 'code', 'name', 'description', 'active', 'order_index']);
            },
            ttl: (int) config('cache.ttl_fetch', 300),
        );
    }

    public function findByIdInCompany(int $id, int $companyId): ?LeaveCategory
    {
        $version = $this->cacheVersionService->get($this->versionNamespace($companyId, 'show'));

        /** @var LeaveCategory|null $category */
        $category = $this->cacheService->remember(
            tag: $this->cacheTag($companyId),
            key: 'v'.$version.':'.$id,
            callback: static fn (): ?LeaveCategory => LeaveCategory::query()
                ->inCompany($companyId)
                ->find($id),
            ttl: (int) config('cache.ttl_fetch', 60),
        );

        return $category;
    }

    public function existsCodeInCompany(int $companyId, string $code, ?int $ignoreId = null): bool
    {
        return LeaveCategory::withTrashed()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public function existsActiveCodeInCompany(int $companyId, string $code): bool
    {
        return LeaveCategory::query()
            ->inCompany($companyId)
            ->where('code', $code)
            ->where('active', true)
            ->exists();
    }

    public function createForCompany(int $companyId, array $data): LeaveCategory
    {
        /** @var LeaveCategory $category */
        $category = LeaveCategory::query()->create([
            ...$data,
            'company_id' => $companyId,
        ]);

        return $category->refresh();
    }

    public function updateInCompany(int $id, int $companyId, array $data): LeaveCategory
    {
        $category = $this->findRequired($id, $companyId);
        $category->fill($data);
        $category->save();

        return $category->refresh();
    }

    public function deleteInCompany(int $id, int $companyId): void
    {
        $category = $this->findRequired($id, $companyId);
        $category->delete();
    }

    private function findRequired(int $id, int $companyId): LeaveCategory
    {
        $category = $this->findByIdInCompany($id, $companyId);

        if ($category instanceof LeaveCategory) {
            return $category;
        }

        throw new NotFoundHttpException('A szabadsag kategoria nem talalhato a kivalasztott company scope-ban.');
    }

    private function cacheTag(int $companyId): string
    {
        return "leave_categories:company:{$companyId}";
    }

    private function versionNamespace(int $companyId, string $segment): string
    {
        return "leave_categories:company:{$companyId}:{$segment}";
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
            return match (strtolower(trim($value))) {
                '1', 'true' => true,
                '0', 'false' => false,
                default => null,
            };
        }

        if (\is_int($value)) {
            return match ($value) {
                1 => true,
                0 => false,
                default => null,
            };
        }

        return null;
    }
}
