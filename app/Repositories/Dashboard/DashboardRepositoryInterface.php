<?php

declare(strict_types=1);

namespace App\Repositories\Dashboard;

interface DashboardRepositoryInterface
{
    /**
     * @return array{users:int,employees:int,companies:int,work_shifts:int}
     */
    public function getStats(int $companyId): array;

    /**
     * @return array<int, array{id:int,name:string,email:string,created_at:string}>
     */
    public function getRecentUsers(int $companyId, int $limit = 5): array;
}

