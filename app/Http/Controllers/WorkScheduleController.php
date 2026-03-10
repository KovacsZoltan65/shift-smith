<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\WorkSchedule\WorkScheduleData;
use App\Data\WorkSchedule\WorkScheduleIndexData;
use App\Http\Requests\WorkSchedule\BulkDeleteRequest;
use App\Http\Requests\WorkSchedule\DeleteRequest;
use App\Http\Requests\WorkSchedule\FetchRequest;
use App\Http\Requests\WorkSchedule\SelectorRequest;
use App\Http\Requests\WorkSchedule\StoreRequest;
use App\Http\Requests\WorkSchedule\UpdateRequest;
use App\Models\WorkSchedule;
use App\Policies\WorkSchedulePolicy;
use App\Services\CurrentCompany;
use App\Services\WorkScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class WorkScheduleController extends Controller
{
    public function __construct(
        private readonly WorkScheduleService $service,
        private readonly CurrentCompany $currentCompany,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize(WorkSchedulePolicy::PERM_VIEW_ANY, WorkSchedule::class);
        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($currentCompanyId === null, 403, __('common.errors.no_company_selected'));

        return Inertia::render('Scheduling/WorkSchedules/Index', [
            'title' => 'Munkabeosztások',
            'filter' => [
                'search' => $request->string('search')->toString() ?: null,
                'field' => $request->string('field')->toString() ?: 'name',
                'order' => $request->string('order')->toString() ?: 'asc',
                'page' => max(1, (int) $request->integer('page', 1)),
                'per_page' => min(max(1, (int) $request->integer('per_page', 10)), 100),
                'company_id' => $currentCompanyId,
            ],
        ]);
    }

    public function fetch(FetchRequest $request): JsonResponse
    {
        $this->authorize(WorkSchedulePolicy::PERM_VIEW_ANY, WorkSchedule::class);
        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($currentCompanyId === null, 403, __('common.errors.no_company_selected'));
        $request->merge(['company_id' => $currentCompanyId]);

        $workSchedules = $this->service->fetch($request);
        $items = WorkScheduleIndexData::collect($workSchedules->items());
        $filter = $request->validatedFilters();
        $filter['company_id'] = $currentCompanyId;

        return response()->json([
            'message' => 'Munkabeosztások sikeresen lekérve.',
            'data' => $items,
            'meta' => [
                'current_page' => $workSchedules->currentPage(),
                'per_page' => $workSchedules->perPage(),
                'total' => $workSchedules->total(),
                'last_page' => $workSchedules->lastPage(),
            ],
            'filter' => $filter,
        ], Response::HTTP_OK);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = (int) $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ])['company_id'];

        $workSchedule = $this->service->find($id, $companyId);
        $this->authorize(WorkSchedulePolicy::PERM_VIEW, $workSchedule);

        return response()->json([
            'message' => 'Munkabeosztás sikeresen lekérve.',
            'data' => WorkScheduleData::fromModel($workSchedule),
        ], Response::HTTP_OK);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize(WorkSchedulePolicy::PERM_CREATE, WorkSchedule::class);

        $created = $this->service->store(WorkScheduleData::from($request->validated()));

        return response()->json([
            'message' => 'A munkabeosztás sikeresen létrehozva.',
            'data' => $created,
        ], Response::HTTP_CREATED);
    }

    public function update(int $id, UpdateRequest $request): JsonResponse
    {
        $companyId = (int) $request->validated('company_id');
        $workSchedule = $this->service->find($id, $companyId);
        $this->authorize(WorkSchedulePolicy::PERM_UPDATE, $workSchedule);

        $updated = $this->service->update($id, WorkScheduleData::from($request->validated()));

        return response()->json([
            'message' => 'Munkabeosztás sikeresen frissítve.',
            'data' => $updated,
        ], Response::HTTP_OK);
    }

    public function destroy(DeleteRequest $request, int $id): JsonResponse
    {
        $companyId = (int) $request->validated('company_id');
        $workSchedule = $this->service->find($id, $companyId);
        $this->authorize(WorkSchedulePolicy::PERM_DELETE, $workSchedule);

        $deleted = $this->service->destroy($id, $companyId);

        return response()->json([
            'message' => $deleted ? 'Törlés sikeres.' : 'Törlés sikertelen.',
            'deleted' => $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function destroyBulk(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize(WorkSchedulePolicy::PERM_DELETE_ANY, WorkSchedule::class);

        $deleted = $this->service->bulkDelete(
            $request->validated('ids'),
            (int) $request->validated('company_id'),
        );

        return response()->json([
            'message' => 'Sikeres törlés.',
            'deleted' => $deleted,
        ], Response::HTTP_OK);
    }

    public function selector(SelectorRequest $request): JsonResponse
    {
        $this->authorize(WorkSchedulePolicy::PERM_VIEW_ANY, WorkSchedule::class);

        return response()->json([
            'data' => $this->service->selector(
                (int) $request->validated('company_id'),
                (bool) $request->boolean('only_published', false),
            ),
        ], Response::HTTP_OK);
    }
}
