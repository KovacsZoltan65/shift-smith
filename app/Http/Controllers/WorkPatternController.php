<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\WorkPattern\WorkPatternData;
use App\Data\WorkPattern\WorkPatternIndexData;
use App\Http\Requests\WorkPattern\BulkDeleteRequest;
use App\Http\Requests\WorkPattern\DeleteRequest;
use App\Http\Requests\WorkPattern\FetchRequest;
use App\Http\Requests\WorkPattern\SelectorRequest;
use App\Http\Requests\WorkPattern\StoreRequest;
use App\Http\Requests\WorkPattern\UpdateRequest;
use App\Models\WorkPattern;
use App\Policies\WorkPatternPolicy;
use App\Services\CurrentCompany;
use App\Services\WorkPatternService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Munkarend controller osztály.
 *
 * HTTP kérések kezelése munkarendek CRUD műveleteihez,
 * selector adatokhoz és a hozzárendelt dolgozók listázásához.
 */
class WorkPatternController extends Controller
{
    /**
     * @param WorkPatternService $service Munkarend service
     */
    public function __construct(
        private readonly WorkPatternService $service,
        private readonly CurrentCompany $currentCompany
    ) {}

    /**
     * Munkarendek lista oldal megjelenítése.
     *
     * @param Request $request HTTP kérés
     * @return InertiaResponse Inertia válasz
     */
    public function index(Request $request): InertiaResponse
    {
        $this->authorize(WorkPatternPolicy::PERM_VIEW_ANY, WorkPattern::class);
        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($currentCompanyId === null, 403, __('common.errors.no_company_selected'));

        return Inertia::render('Scheduling/WorkPatterns/Index', [
            'title' => __('work_patterns.title'),
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

    /**
     * Munkarendek listázása JSON formátumban.
     *
     * @param FetchRequest $request Validált kérés
     * @return JsonResponse Lapozott lista
     */
    public function fetch(FetchRequest $request): JsonResponse
    {
        $this->authorize(WorkPatternPolicy::PERM_VIEW_ANY, WorkPattern::class);
        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($currentCompanyId === null, 403, __('common.errors.no_company_selected'));
        $request->merge(['company_id' => $currentCompanyId]);

        $workPatterns = $this->service->fetch($request);
        $items = WorkPatternIndexData::collect($workPatterns->items());
        $filter = $request->validatedFilters();
        $filter['company_id'] = $currentCompanyId;

        return response()->json([
            'message' => __('work_patterns.messages.fetch_success'),
            'data' => $items,
            'meta' => [
                'current_page' => $workPatterns->currentPage(),
                'per_page' => $workPatterns->perPage(),
                'total' => $workPatterns->total(),
                'last_page' => $workPatterns->lastPage(),
            ],
            'filter' => $filter,
        ], Response::HTTP_OK);
    }

    /**
     * Egy munkarend lekérése azonosító alapján.
     *
     * @param int $id Munkarend azonosító
     * @return JsonResponse Munkarend adatok JSON-ben
     */
    public function getWorkPattern(Request $request, int $id): JsonResponse
    {
        $companyId = (int) $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ])['company_id'];

        $workPattern = $this->service->find($id, $companyId);
        $this->authorize(WorkPatternPolicy::PERM_VIEW, $workPattern);

        return response()->json([
            'message' => __('work_patterns.messages.show_success'),
            'data' => WorkPatternData::fromModel($workPattern),
        ], Response::HTTP_OK);
    }

    /**
     * Új munkarend létrehozása.
     *
     * @param StoreRequest $request Validált kérés
     * @return JsonResponse Létrehozott munkarend JSON-ben
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize(WorkPatternPolicy::PERM_CREATE, WorkPattern::class);

        $created = $this->service->store(WorkPatternData::from($request->validated()));

        return response()->json([
            'message' => __('work_patterns.messages.created_success'),
            'data' => $created,
        ], Response::HTTP_CREATED);
    }

    /**
     * Munkarend frissítése.
     *
     * @param int $id Munkarend azonosító
     * @param UpdateRequest $request Validált kérés
     * @return JsonResponse Frissített munkarend JSON-ben
     */
    public function update(int $id, UpdateRequest $request): JsonResponse
    {
        $companyId = (int) $request->validated('company_id');
        $workPattern = $this->service->find($id, $companyId);
        $this->authorize(WorkPatternPolicy::PERM_UPDATE, $workPattern);

        $updated = $this->service->update($id, WorkPatternData::from($request->validated()));

        return response()->json([
            'message' => __('work_patterns.messages.updated_success'),
            'data' => $updated,
        ], Response::HTTP_OK);
    }

    /**
     * Több munkarend törlése egyszerre.
     *
     * @param BulkDeleteRequest $request Validált kérés
     * @return JsonResponse Törlés eredménye
     */
    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize(WorkPatternPolicy::PERM_DELETE_ANY, WorkPattern::class);

        $deleted = $this->service->bulkDelete(
            $request->validated('ids'),
            (int) $request->validated('company_id')
        );

        return response()->json([
            'message' => __('common.delete_success'),
            'deleted' => $deleted,
        ], Response::HTTP_OK);
    }

    /**
     * Több munkarend törlése egyszerre (backward compatible alias).
     *
     * @param BulkDeleteRequest $request Validált kérés
     * @return JsonResponse Törlés eredménye
     */
    public function destroyBulk(BulkDeleteRequest $request): JsonResponse
    {
        return $this->bulkDelete($request);
    }

    /**
     * Egy munkarend törlése.
     *
     * @param int $id Munkarend azonosító
     * @return JsonResponse Törlés eredménye
     */
    public function destroy(DeleteRequest $request, int $id): JsonResponse
    {
        $companyId = (int) $request->validated('company_id');
        $workPattern = $this->service->find($id, $companyId);
        $this->authorize(WorkPatternPolicy::PERM_DELETE, $workPattern);
        $deleted = $this->service->destroy($id, $companyId);

        return response()->json([
            'message' => $deleted ? __('work_patterns.messages.deleted_success') : __('common.delete_failed'),
            'deleted' => (bool) $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Munkarend selector lista lekérése.
     *
     * @param SelectorRequest $request Validált kérés
     * @return JsonResponse Selector lista
     */
    public function getToSelect(SelectorRequest $request): JsonResponse
    {
        $companyId = (int) $request->validated('company_id');
        $onlyActive = (bool) $request->boolean('only_active', true);

        return response()->json(
            $this->service->getToSelect($companyId, $onlyActive),
            Response::HTTP_OK
        );
    }

    /**
     * Munkarendhez rendelt dolgozók listázása.
     *
     * @param int $id Munkarend azonosító
     * @return JsonResponse Dolgozó lista
     */
    public function getEmployees(Request $request, int $id): JsonResponse
    {
        $companyId = (int) $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ])['company_id'];

        $workPattern = $this->service->find($id, $companyId);
        $this->authorize(WorkPatternPolicy::PERM_VIEW, $workPattern);

        return response()->json([
            'message' => __('work_patterns.messages.assignees_fetch_success'),
            'data' => $this->service->getAssignedEmployees($id, $companyId),
        ], Response::HTTP_OK);
    }
}
