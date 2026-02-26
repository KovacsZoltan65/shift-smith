<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Dashboard\DashboardRepositoryInterface;

final class DashboardService
{
    public function __construct(
        private readonly DashboardRepositoryInterface $dashboardRepository,
    ) {}

    /**
     * @return array{
     *   stats: array{users:int,employees:int,companies:int,work_shifts:int},
     *   recentUsers: array<int, array{id:int,name:string,email:string,created_at:string}>
     * }
     */
    public function getDashboardStats(int $selectedCompanyId): array
    {
        return [
            'stats' => $this->dashboardRepository->getStats($selectedCompanyId),
            'recentUsers' => $this->dashboardRepository->getRecentUsers($selectedCompanyId),
        ];
    }
}

