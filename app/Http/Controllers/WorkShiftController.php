<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WorkShift\BulkDeleteRequest;
use App\Http\Requests\WorkShift\DeleteRequest;
use App\Http\Requests\WorkShift\FetchRequest;
use App\Http\Requests\WorkShift\IndexRequest;
use App\Http\Requests\WorkShift\StoreRequest;
use App\Http\Requests\WorkShift\UpdateRequest;
use App\Models\WorkShift;
use App\Policies\WorkShiftPolicy;
use App\Services\CurrentCompany;
use App\Services\WorkShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

final class WorkShiftController extends Controller
{
    public function __construct(
        private readonly WorkShiftService $service,
        private readonly CurrentCompany $currentCompany,
    ) {}

    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize(WorkShiftPolicy::PERM_VIEW, WorkShift::class);

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($currentCompanyId === null, 403, 'No company selected');

        $filter = $request->validatedFilters();
        $filter['company_id'] = $currentCompanyId;

        return Inertia::render('WorkShifts/Index', [
            'title' => 'Műszakok',
            'filter' => $filter,
        ]);
    }

    public function fetch(FetchRequest $request): JsonResponse
    {
        $this->authorize(WorkShiftPolicy::PERM_VIEW, WorkShift::class);

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($currentCompanyId === null, 403, 'No company selected');

        $workShifts = $this->service->fetch($request, $currentCompanyId);
        $filter = $request->validatedFilters();

        return response()->json([
            'message' => 'Műszakok sikeresen lekérve.',
            'data' => $workShifts->items(),
            'meta' => [
                'current_page' => $workShifts->currentPage(),
                'per_page' => $workShifts->perPage(),
                'total' => $workShifts->total(),
                'last_page' => $workShifts->lastPage(),
            ],
            'filter' => $filter,
        ], Response::HTTP_OK);
    }

    public function getWorkShift(Request $request, int $id): JsonResponse
    {
        $this->authorize(WorkShiftPolicy::PERM_VIEW, WorkShift::class);

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($currentCompanyId === null, 403, 'No company selected');

        $workShift = $this->service->find($id, $currentCompanyId);
        $this->authorize(WorkShiftPolicy::PERM_VIEW, $workShift);

        return response()->json([
            'message' => 'Műszak sikeresen lekérve.',
            'data' => $workShift,
        ], Response::HTTP_OK);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize(WorkShiftPolicy::PERM_CREATE, WorkShift::class);

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($currentCompanyId === null, 403, 'No company selected');

        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $created = $this->service->store($data, $currentCompanyId);

        return response()->json([
            'message' => 'A műszak sikeresen létrehozva.',
            'data' => $created,
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateRequest $request, int $id): JsonResponse
    {
        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($currentCompanyId === null, 403, 'No company selected');

        $workShift = $this->service->find($id, $currentCompanyId);
        $this->authorize(WorkShiftPolicy::PERM_UPDATE, $workShift);

        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $updated = $this->service->update($id, $data, $currentCompanyId);

        return response()->json([
            'message' => 'A műszak sikeresen frissítve.',
            'data' => $updated,
        ], Response::HTTP_OK);
    }

    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize(WorkShiftPolicy::PERM_DELETE_ANY, WorkShift::class);

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($currentCompanyId === null, 403, 'No company selected');

        /** @var array{ids:list<int>} $data */
        $data = $request->validated();
        $deleted = $this->service->bulkDelete($data['ids'], $currentCompanyId);

        return response()->json([
            'message' => 'Sikeres törlés.',
            'deleted' => $deleted,
        ], Response::HTTP_OK);
    }

    public function destroy(DeleteRequest $request, int $id): JsonResponse
    {
        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($currentCompanyId === null, 403, 'No company selected');

        $workShift = $this->service->find($id, $currentCompanyId);
        $this->authorize(WorkShiftPolicy::PERM_DELETE, $workShift);

        $deleted = $this->service->destroy($id, $currentCompanyId);

        return response()->json([
            'message' => $deleted ? 'Törlés sikeres.' : 'Törlés sikertelen.',
            'deleted' => $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function getToSelect(FetchRequest $request): JsonResponse
    {
        $this->authorize(WorkShiftPolicy::PERM_VIEW, WorkShift::class);

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($currentCompanyId === null, 403, 'No company selected');

        $params = [
            'search' => $request->string('search')->toString() ?: null,
            'only_active' => $request->boolean('only_active', true),
            'limit' => max(1, min(100, (int) $request->integer('limit', 50))),
        ];

        return response()->json(
            $this->service->getToSelect($params, $currentCompanyId),
            Response::HTTP_OK
        );
    }
}
