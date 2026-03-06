<?php

declare(strict_types=1);

namespace App\Services\Org;

use App\Repositories\Org\OrgHierarchyRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

final class OrgHierarchyPathService
{
    private const MAX_DEPTH = 200;

    public function __construct(
        private readonly OrgHierarchyRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array<int, array{id:int,label:string}>
     */
    public function getPath(int $companyId, int $employeeId, CarbonInterface $atDate): array
    {
        $employee = $this->repository->findEmployeeInCompany($companyId, $employeeId);
        if ($employee === null) {
            throw (new ModelNotFoundException())->setModel('employee', [$employeeId]);
        }

        $pathIds = [$employeeId];
        $visited = [$employeeId => true];
        $cursor = $employeeId;
        $depth = 0;

        while ($depth < self::MAX_DEPTH) {
            $parentId = $this->repository->findActiveSupervisorEmployeeId($companyId, $cursor, $atDate);
            if ($parentId === null) {
                break;
            }

            if (isset($visited[$parentId])) {
                throw new RuntimeException('Cycle detected while resolving hierarchy path.');
            }

            $visited[$parentId] = true;
            $pathIds[] = $parentId;
            $cursor = $parentId;
            $depth++;
        }

        if ($depth >= self::MAX_DEPTH) {
            throw new RuntimeException('Hierarchy path depth limit exceeded.');
        }

        $orderedIds = array_reverse($pathIds);
        $employees = $this->repository->getEmployeesByIdsInCompany($companyId, $orderedIds)->keyBy('id');

        $result = [];
        foreach ($orderedIds as $id) {
            $employee = $employees->get($id);
            $label = is_object($employee)
                ? trim(((string) ($employee->last_name ?? '')).' '.((string) ($employee->first_name ?? '')))
                : '';

            if ($label === '') {
                $label = '#'.$id;
            }

            $result[] = [
                'id' => (int) $id,
                'label' => $label,
            ];
        }

        return $result;
    }
}
