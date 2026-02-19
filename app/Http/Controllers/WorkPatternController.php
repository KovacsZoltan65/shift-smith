<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\WorkPattern\WorkPatternData;
use App\Data\WorkPattern\WorkPatternIndexData;
use App\Http\Requests\WorkPattern\BulkDeleteRequest;
use App\Http\Requests\WorkPattern\FetchRequest;
use App\Http\Requests\WorkPattern\SelectorRequest;
use App\Http\Requests\WorkPattern\StoreRequest;
use App\Http\Requests\WorkPattern\UpdateRequest;
use App\Models\WorkPattern;
use App\Policies\WorkPatternPolicy;
use App\Services\WorkPatternService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Munkarend controller osztály.
 *
 * HTTP kérések kezelése munkarendek CRUD műveleteihez.
 */
class WorkPatternController extends Controller
{
    /**
     * @param WorkPatternService $service Munkarend service
     */
    public function __construct(
        private readonly WorkPatternService $service
    ) {}

    /**
     * Munkarendek lista oldal megjelenítése.
     *
     * @param FetchRequest $request Validált kérés
     * @return InertiaResponse Inertia válasz
     */
    public function index(FetchRequest $request): InertiaResponse
    {
        $this->authorize(WorkPatternPolicy::PERM_VIEW_ANY, WorkPattern::class);

        return Inertia::render('Scheduling/WorkPatterns/Index', [
            'title' => 'Munkarendek',
            'filter' => $request->validatedFilters(),
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

        $workPatterns = $this->service->fetch($request);
        $items = WorkPatternIndexData::collect($workPatterns->items());

        return response()->json([
            'message' => 'Munkarendek sikeresen lekérve.',
            'data' => $items,
            'meta' => [
                'current_page' => $workPatterns->currentPage(),
                'per_page' => $workPatterns->perPage(),
                'total' => $workPatterns->total(),
                'last_page' => $workPatterns->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
    }

    /**
     * Egy munkarend lekérése azonosító alapján.
     *
     * @param int $id Munkarend azonosító
     * @return JsonResponse Munkarend adatok JSON-ben
     */
    public function getWorkPattern(int $id): JsonResponse
    {
        $workPattern = $this->service->find($id);
        $this->authorize(WorkPatternPolicy::PERM_VIEW, $workPattern);

        return response()->json([
            'message' => 'Munkarend sikeresen lekérve.',
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
            'message' => 'A munkarend sikeresen létrehozva.',
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
        $workPattern = $this->service->find($id);
        $this->authorize(WorkPatternPolicy::PERM_UPDATE, $workPattern);

        $updated = $this->service->update($id, WorkPatternData::from($request->validated()));

        return response()->json([
            'message' => 'Munkarend sikeresen frissítve.',
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

        $deleted = $this->service->bulkDelete($request->validated('ids'));

        return response()->json([
            'message' => 'Sikeres törlés.',
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
    public function destroy(int $id): JsonResponse
    {
        $workPattern = $this->service->find($id);
        $this->authorize(WorkPatternPolicy::PERM_DELETE, $workPattern);
        $deleted = $this->service->destroy($id);

        return response()->json([
            'message' => $deleted ? 'Törlés sikeres.' : 'Törlés sikertelen.',
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
}
