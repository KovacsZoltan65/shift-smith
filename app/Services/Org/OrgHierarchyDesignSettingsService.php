<?php

declare(strict_types=1);

namespace App\Services\Org;

use App\Repositories\Org\OrgHierarchyDesignSettingsRepositoryInterface;
use App\Services\Cache\CacheVersionService;
use App\Services\SettingsResolverService;

final class OrgHierarchyDesignSettingsService
{
    public const KEY_VIEW_MODE = 'ui.hierarchy.view_mode';
    public const KEY_DENSITY = 'ui.hierarchy.density';
    public const KEY_SHOW_POSITION = 'ui.hierarchy.show_position';

    public function __construct(
        private readonly OrgHierarchyDesignSettingsRepositoryInterface $repository,
        private readonly SettingsResolverService $settingsResolver,
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    /**
     * @return array{view_mode:'explorer'|'network',density:'compact'|'comfortable',show_position:bool}
     */
    public function effectiveForUser(int $companyId, int $userId): array
    {
        return [
            'view_mode' => $this->normalizeViewMode($this->resolveSetting(self::KEY_VIEW_MODE, $companyId, $userId)),
            'density' => $this->normalizeDensity($this->resolveSetting(self::KEY_DENSITY, $companyId, $userId)),
            'show_position' => $this->normalizeBool($this->resolveSetting(self::KEY_SHOW_POSITION, $companyId, $userId), true),
        ];
    }

    /**
     * @param array{view_mode:string,density:string,show_position:bool} $settings
     * @return array{view_mode:'explorer'|'network',density:'compact'|'comfortable',show_position:bool}
     */
    public function saveForUser(int $companyId, int $userId, int $actorUserId, array $settings): array
    {
        $normalized = [
            'view_mode' => $this->normalizeViewMode($settings['view_mode'] ?? null),
            'density' => $this->normalizeDensity($settings['density'] ?? null),
            'show_position' => $this->normalizeBool($settings['show_position'] ?? null, true),
        ];

        $this->repository->upsertUserSetting(
            userId: $userId,
            companyId: $companyId,
            key: self::KEY_VIEW_MODE,
            value: $normalized['view_mode'],
            updatedBy: $actorUserId,
            type: 'string',
        );
        $this->repository->upsertUserSetting(
            userId: $userId,
            companyId: $companyId,
            key: self::KEY_DENSITY,
            value: $normalized['density'],
            updatedBy: $actorUserId,
            type: 'string',
        );
        $this->repository->upsertUserSetting(
            userId: $userId,
            companyId: $companyId,
            key: self::KEY_SHOW_POSITION,
            value: $normalized['show_position'],
            updatedBy: $actorUserId,
            type: 'bool',
        );

        $this->cacheVersionService->bump("effective_settings:{$companyId}:all");

        return $normalized;
    }

    private function resolveSetting(string $key, int $companyId, int $userId): mixed
    {
        $resolved = $this->settingsResolver->effectiveValue($key, [
            'company_id' => $companyId,
            'user_id' => $userId,
        ]);

        return $resolved['value'] ?? null;
    }

    private function normalizeViewMode(mixed $value): string
    {
        $candidate = is_string($value) ? strtolower(trim($value)) : '';
        return in_array($candidate, ['explorer', 'network'], true) ? $candidate : 'explorer';
    }

    private function normalizeDensity(mixed $value): string
    {
        $candidate = is_string($value) ? strtolower(trim($value)) : '';
        return in_array($candidate, ['compact', 'comfortable'], true) ? $candidate : 'comfortable';
    }

    private function normalizeBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (\is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $lower = strtolower(trim($value));
            if (in_array($lower, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($lower, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }
}
