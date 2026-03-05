<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\MonthClosureRepositoryInterface;
use App\Models\MonthClosure;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MonthClosureRepository implements MonthClosureRepositoryInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    public function findActiveForMonth(int $companyId, int $year, int $month): ?MonthClosure
    {
        return $this->scopedQuery($companyId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    public function findWithTrashedForMonth(int $companyId, int $year, int $month): ?MonthClosure
    {
        return $this->scopedQuery($companyId, true)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    public function findOrFailScoped(int $id, int $companyId): MonthClosure
    {
        /** @var MonthClosure $closure */
        $closure = $this->scopedQuery($companyId)->findOrFail($id);

        return $closure;
    }

    public function findActiveWithinRange(int $companyId, string $startDate, string $endDate): Collection
    {
        $startYear = (int) substr($startDate, 0, 4);
        $startMonth = (int) substr($startDate, 5, 2);
        $endYear = (int) substr($endDate, 0, 4);
        $endMonth = (int) substr($endDate, 5, 2);
        $startCode = ($startYear * 100) + $startMonth;
        $endCode = ($endYear * 100) + $endMonth;

        return $this->scopedQuery($companyId)
            ->whereRaw('((year * 100) + month) between ? and ?', [$startCode, $endCode])
            ->orderBy('year')
            ->orderBy('month')
            ->get();
    }

    public function create(array $attributes): MonthClosure
    {
        /** @var MonthClosure $closure */
        $closure = MonthClosure::query()->create($attributes);

        return $closure->fresh(['closedBy']) ?? $closure;
    }

    public function restore(MonthClosure $closure, array $attributes): MonthClosure
    {
        $closure->restore();
        $closure->fill($attributes);
        $closure->deleted_at = null;
        $closure->save();
        $closure->refresh();

        return $closure->loadMissing('closedBy');
    }

    public function delete(MonthClosure $closure): bool
    {
        return (bool) $closure->delete();
    }

    private function scopedQuery(int $companyId, bool $withTrashed = false): Builder
    {
        $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
        $query = MonthClosure::query()
            ->when($withTrashed, static fn (Builder $builder): Builder => $builder->withTrashed())
            ->where('company_id', $companyId)
            ->whereHas('company', static function (Builder $builder) use ($tenantGroupId): void {
                $builder->where('tenant_group_id', $tenantGroupId);
            });

        return $query->with(['closedBy:id,name']);
    }
}
