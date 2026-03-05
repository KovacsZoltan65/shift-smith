<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\OrgPositionLevel\IndexRequest;
use App\Http\Requests\OrgPositionLevel\StoreRequest;
use App\Http\Requests\OrgPositionLevel\UpdateRequest;
use App\Models\PositionOrgLevel;
use App\Policies\PositionOrgLevelPolicy;
use App\Services\CurrentCompany;
use App\Services\Org\PositionOrgLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

final class OrgPositionLevelController extends Controller
{
    public function __construct(
        private readonly PositionOrgLevelService $service,
        private readonly CurrentCompany $currentCompany,
    ) {
    }

    public function index(Request $request): InertiaResponse
    {
        $this->authorize(PositionOrgLevelPolicy::PERM_VIEW_ANY, PositionOrgLevel::class);

        return Inertia::render('PositionOrgLevels/Index', [
            'title' => 'Position szint mapping',
            'filter' => [
                'q' => $request->string('q')->toString() ?: null,
                'org_level' => $request->string('org_level')->toString() ?: null,
                'active' => $request->has('active') ? $request->boolean('active') : null,
            ],
            'org_levels' => \App\Models\Employee::ORG_LEVELS,
        ]);
    }

    public function fetch(IndexRequest $request): JsonResponse
    {
        $this->authorize(PositionOrgLevelPolicy::PERM_VIEW_ANY, PositionOrgLevel::class);
        $companyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! is_int($companyId) || $companyId <= 0, 403, 'No company selected');

        $paginator = $this->service->listMappings($companyId, $request->validated());

        return response()->json([
            'message' => 'Position-szint mappingek lekérve.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize(PositionOrgLevelPolicy::PERM_CREATE, PositionOrgLevel::class);
        $companyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! is_int($companyId) || $companyId <= 0, 403, 'No company selected');

        $row = $this->service->upsertMapping(
            companyId: $companyId,
            positionLabel: (string) $request->validated('position_label'),
            orgLevel: (string) $request->validated('org_level'),
            active: (bool) $request->boolean('active', true)
        );

        return response()->json([
            'message' => 'Position-szint mapping mentve.',
            'data' => $row,
        ], Response::HTTP_CREATED);
    }

    public function update(int $id, UpdateRequest $request): JsonResponse
    {
        $this->authorize(PositionOrgLevelPolicy::PERM_UPDATE, PositionOrgLevel::class);
        $companyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! is_int($companyId) || $companyId <= 0, 403, 'No company selected');

        $row = $this->service->updateMapping(
            companyId: $companyId,
            id: $id,
            positionLabel: (string) $request->validated('position_label'),
            orgLevel: (string) $request->validated('org_level'),
            active: (bool) $request->boolean('active')
        );

        return response()->json([
            'message' => 'Position-szint mapping frissítve.',
            'data' => $row,
        ], Response::HTTP_OK);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $this->authorize(PositionOrgLevelPolicy::PERM_DELETE, PositionOrgLevel::class);
        $companyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! is_int($companyId) || $companyId <= 0, 403, 'No company selected');

        $deleted = $this->service->deleteMapping($companyId, $id);

        return response()->json([
            'message' => $deleted ? 'Position-szint mapping törölve.' : 'A mapping nem található.',
            'deleted' => $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_NOT_FOUND);
    }
}

