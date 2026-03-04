<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\MonthClosureRepositoryInterface;
use App\Models\MonthClosure;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MonthClosureService
{
    public function __construct(
        private readonly MonthClosureRepositoryInterface $repository,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function assertMonthEditableOrFail(int $companyId, \DateTimeInterface|string $date): void
    {
        $parsed = $date instanceof \DateTimeInterface
            ? CarbonImmutable::instance($date)
            : CarbonImmutable::parse((string) $date);

        $state = $this->stateForMonth($companyId, (int) $parsed->format('Y'), (int) $parsed->format('m'));

        if (($state['is_closed'] ?? false) !== true) {
            return;
        }

        throw new AuthorizationException(sprintf(
            'A(z) %04d-%02d hónap lezárva, a szerkesztés nem engedélyezett.',
            (int) $state['year'],
            (int) $state['month'],
        ));
    }

    /**
     * @return array{
     *   id:int|null,
     *   year:int,
     *   month:int,
     *   is_closed:bool,
     *   closed_at:string|null,
     *   closed_by_user_id:int|null,
     *   closed_by_name:string|null,
     *   note:string|null
     * }
     */
    public function stateForMonth(int $companyId, int $year, int $month): array
    {
        $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
        $namespace = CacheNamespaces::tenantMonthClosures($tenantGroupId);
        $version = $this->cacheVersionService->get($namespace);
        $monthKey = $this->monthKey($year, $month);

        /** @var array{
         *   id:int|null,
         *   year:int,
         *   month:int,
         *   is_closed:bool,
         *   closed_at:string|null,
         *   closed_by_user_id:int|null,
         *   closed_by_name:string|null,
         *   note:string|null
         * } */
        return $this->cacheService->remember(
            tag: 'month_closures',
            key: "v{$version}:company:{$companyId}:month:{$monthKey}",
            callback: function () use ($companyId, $year, $month): array {
                $closure = $this->repository->findActiveForMonth($companyId, $year, $month);

                return $this->serializeState($year, $month, $closure);
            },
            ttl: (int) config('cache.ttl_fetch', 60)
        );
    }

    /**
     * @return array<int, array{
     *   id:int|null,
     *   year:int,
     *   month:int,
     *   is_closed:bool,
     *   closed_at:string|null,
     *   closed_by_user_id:int|null,
     *   closed_by_name:string|null,
     *   note:string|null
     * }>
     */
    public function statesWithinRange(int $companyId, string $startDate, string $endDate): array
    {
        $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
        $namespace = CacheNamespaces::tenantMonthClosures($tenantGroupId);
        $version = $this->cacheVersionService->get($namespace);

        /** @var array<int, array{
         *   id:int|null,
         *   year:int,
         *   month:int,
         *   is_closed:bool,
         *   closed_at:string|null,
         *   closed_by_user_id:int|null,
         *   closed_by_name:string|null,
         *   note:string|null
         * }> */
        return $this->cacheService->remember(
            tag: 'month_closures',
            key: "v{$version}:company:{$companyId}:range:{$startDate}:{$endDate}",
            callback: function () use ($companyId, $startDate, $endDate): array {
                return $this->repository->findActiveWithinRange($companyId, $startDate, $endDate)
                    ->map(fn (MonthClosure $closure): array => $this->serializeState(
                        (int) $closure->year,
                        (int) $closure->month,
                        $closure
                    ))
                    ->values()
                    ->all();
            },
            ttl: (int) config('cache.ttl_fetch', 60)
        );
    }

    public function close(int $companyId, int $actorUserId, int $year, int $month, ?string $note = null): MonthClosure
    {
        $this->assertValidMonth($year, $month);
        $existing = $this->repository->findWithTrashedForMonth($companyId, $year, $month);

        if ($existing !== null && $existing->deleted_at === null) {
            throw ValidationException::withMessages([
                'month' => 'Az adott hónap már le van zárva.',
            ]);
        }

        $closure = DB::transaction(function () use ($companyId, $actorUserId, $year, $month, $note, $existing): MonthClosure {
            $payload = [
                'company_id' => $companyId,
                'year' => $year,
                'month' => $month,
                'closed_at' => now(),
                'closed_by_user_id' => $actorUserId,
                'note' => $note,
            ];

            if ($existing !== null) {
                return $this->repository->restore($existing, [
                    'closed_at' => $payload['closed_at'],
                    'closed_by_user_id' => $payload['closed_by_user_id'],
                    'note' => $payload['note'],
                ]);
            }

            return $this->repository->create($payload);
        });

        $this->invalidateAfterWrite();

        return $closure;
    }

    public function reopen(int $companyId, int $id): bool
    {
        $closure = $this->repository->findOrFailScoped($id, $companyId);
        $deleted = $this->repository->delete($closure);

        if ($deleted) {
            $this->invalidateAfterWrite();
        }

        return $deleted;
    }

    public function findForCompany(int $companyId, int $id): MonthClosure
    {
        return $this->repository->findOrFailScoped($id, $companyId);
    }

    /**
     * @return list<string>
     */
    public function closedMonthKeysWithinRange(int $companyId, string $startDate, string $endDate): array
    {
        return collect($this->statesWithinRange($companyId, $startDate, $endDate))
            ->filter(static fn (array $state): bool => ($state['is_closed'] ?? false) === true)
            ->map(fn (array $state): string => $this->monthKey((int) $state['year'], (int) $state['month']))
            ->values()
            ->all();
    }

    private function invalidateAfterWrite(): void
    {
        $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
        $this->cacheVersionService->bump(CacheNamespaces::tenantMonthClosures($tenantGroupId));
        $this->cacheVersionService->bump(CacheNamespaces::tenantWorkScheduleAssignments($tenantGroupId));
    }

    private function assertValidMonth(int $year, int $month): void
    {
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            throw ValidationException::withMessages([
                'month' => 'Érvénytelen év vagy hónap.',
            ]);
        }
    }

    /**
     * @return array{
     *   id:int|null,
     *   year:int,
     *   month:int,
     *   is_closed:bool,
     *   closed_at:string|null,
     *   closed_by_user_id:int|null,
     *   closed_by_name:string|null,
     *   note:string|null
     * }
     */
    private function serializeState(int $year, int $month, ?MonthClosure $closure): array
    {
        return [
            'id' => $closure?->id !== null ? (int) $closure->id : null,
            'year' => $year,
            'month' => $month,
            'is_closed' => $closure !== null,
            'closed_at' => $closure?->closed_at?->format('Y-m-d H:i:s'),
            'closed_by_user_id' => $closure?->closed_by_user_id !== null ? (int) $closure->closed_by_user_id : null,
            'closed_by_name' => $closure?->closedBy?->name !== null ? (string) $closure->closedBy->name : null,
            'note' => $closure?->note !== null ? (string) $closure->note : null,
        ];
    }

    private function monthKey(int $year, int $month): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }
}
