<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\MonthClosure;
use Illuminate\Support\Collection;

interface MonthClosureRepositoryInterface
{
    public function findActiveForMonth(int $companyId, int $year, int $month): ?MonthClosure;

    public function findWithTrashedForMonth(int $companyId, int $year, int $month): ?MonthClosure;

    public function findOrFailScoped(int $id, int $companyId): MonthClosure;

    /**
     * @return Collection<int, MonthClosure>
     */
    public function findActiveWithinRange(int $companyId, string $startDate, string $endDate): Collection;

    /**
     * @param array{
     *   company_id:int,
     *   year:int,
     *   month:int,
     *   closed_at:\Illuminate\Support\CarbonInterface|string,
     *   closed_by_user_id?:int|null,
     *   note?:string|null
     * } $attributes
     */
    public function create(array $attributes): MonthClosure;

    /**
     * @param array{
     *   closed_at:\Illuminate\Support\CarbonInterface|string,
     *   closed_by_user_id?:int|null,
     *   note?:string|null
     * } $attributes
     */
    public function restore(MonthClosure $closure, array $attributes): MonthClosure;

    public function delete(MonthClosure $closure): bool;
}
