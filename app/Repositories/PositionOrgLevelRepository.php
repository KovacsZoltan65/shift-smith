<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PositionOrgLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PositionOrgLevelRepository implements PositionOrgLevelRepositoryInterface
{
    public function fetch(int $companyId, array $filters): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(max(1, (int) ($filters['per_page'] ?? 10)), 100);
        $term = isset($filters['q']) && is_string($filters['q']) ? trim($filters['q']) : null;
        $orgLevel = isset($filters['org_level']) && is_string($filters['org_level']) ? trim($filters['org_level']) : null;
        $active = array_key_exists('active', $filters) ? (bool) $filters['active'] : null;

        return PositionOrgLevel::query()
            ->where('company_id', $companyId)
            ->when($term !== null && $term !== '', function ($query) use ($term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('position_label', 'like', "%{$term}%")
                        ->orWhere('position_key', 'like', "%{$term}%");
                });
            })
            ->when($orgLevel !== null && $orgLevel !== '', fn ($query) => $query->where('org_level', $orgLevel))
            ->when($active !== null, fn ($query) => $query->where('active', $active))
            ->orderBy('position_label')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function findByIdInCompany(int $id, int $companyId): ?PositionOrgLevel
    {
        /** @var PositionOrgLevel|null $row */
        $row = PositionOrgLevel::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->first();

        return $row;
    }

    public function activeMapByCompany(int $companyId): array
    {
        return PositionOrgLevel::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->pluck('org_level', 'position_key')
            ->mapWithKeys(static fn ($value, $key): array => [(string) $key => (string) $value])
            ->all();
    }

    public function upsert(int $companyId, string $positionKey, string $positionLabel, string $orgLevel, bool $active): PositionOrgLevel
    {
        /** @var PositionOrgLevel $row */
        $row = PositionOrgLevel::query()->updateOrCreate(
            ['company_id' => $companyId, 'position_key' => $positionKey],
            [
                'position_label' => $positionLabel,
                'org_level' => $orgLevel,
                'active' => $active,
            ]
        );

        return $row->refresh();
    }

    public function updateInCompany(int $id, int $companyId, array $payload): PositionOrgLevel
    {
        /** @var PositionOrgLevel $row */
        $row = PositionOrgLevel::query()
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->findOrFail($id);

        $row->fill($payload);
        $row->save();

        return $row->refresh();
    }

    public function deleteInCompany(int $id, int $companyId): bool
    {
        /** @var PositionOrgLevel|null $row */
        $row = PositionOrgLevel::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->first();

        if (! $row instanceof PositionOrgLevel) {
            return false;
        }

        return (bool) $row->delete();
    }
}

