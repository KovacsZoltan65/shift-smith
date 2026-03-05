<?php

declare(strict_types=1);

namespace App\Services\Cache;

final class CacheNamespaces
{
    public static function tenantWorkSchedules(int $tenantGroupId): string
    {
        return "tenant:{$tenantGroupId}:work_schedules";
    }

    public static function tenantWorkScheduleAssignments(int $tenantGroupId): string
    {
        return "tenant:{$tenantGroupId}:work_schedule_assignments";
    }

    public static function tenantMonthClosures(int $tenantGroupId): string
    {
        return "tenant:{$tenantGroupId}:month_closures";
    }

    public static function tenantAbsences(int $tenantGroupId): string
    {
        return "tenant:{$tenantGroupId}:absences";
    }

    public static function tenantOrgHierarchy(int $tenantGroupId, int $companyId): string
    {
        return "tenant:{$tenantGroupId}:org:{$companyId}";
    }
}
