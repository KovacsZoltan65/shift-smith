<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Position\PositionData;
use App\Data\Position\PositionIndexData;
use App\Http\Requests\Position\BulkDeleteRequest;
use App\Http\Requests\Position\IndexRequest;
use App\Http\Requests\Position\SelectorRequest;
use App\Http\Requests\Position\StoreRequest;
use App\Http\Requests\Position\UpdateRequest;
use App\Models\Position;
use App\Policies\PositionPolicy;
use App\Services\PositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class PositionController extends Controller
{
    public function __construct(
        private readonly PositionService $service
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize(PositionPolicy::PERM_VIEW_ANY, Position::class);

        return Inertia::render('HR/Positions/Index', [
            'title' => 'Pozíciók',
            'filter' => [
                'search' => $request->string('search')->toString() ?: null,
                'field' => $request->string('field')->toString() ?: 'id',
                'order' => $request->string('order')->toString() ?: 'desc',
                'page' => max(1, (int) $request->integer('page', 1)),
                'per_page' => min(max(1, (int) $request->integer('per_page', 10)), 100),
                'company_id' => $request->filled('company_id') ? (int) $request->integer('company_id') : null,
            ],
        ]);
    }

    public function fetch(IndexRequest $request): JsonResponse
    {
        $this->authorize(PositionPolicy::PERM_VIEW_ANY, Position::class);

        $positions = $this->service->fetch($request);
        $items = PositionIndexData::collect($positions->items());

        return response()->json([
            'message' => 'Pozíciók sikeresen lekérve.',
            'data' => $items,
            'meta' => [
                'current_page' => $positions->currentPage(),
                'per_page' => $positions->perPage(),
                'total' => $positions->total(),
                'last_page' => $positions->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
    }

    public function getPosition(Request $request, int $id): JsonResponse
    {
        $companyId = (int) $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ])['company_id'];

        $position = $this->service->find($id, $companyId);
        $this->authorize(PositionPolicy::PERM_VIEW, $position);

        return response()->json([
            'message' => 'Pozíció sikeresen lekérve.',
            'data' => PositionData::fromModel($position),
        ], Response::HTTP_OK);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize(PositionPolicy::PERM_CREATE, Position::class);

        $created = $this->service->store(PositionData::from($request->validated()));

        return response()->json([
            'message' => 'A pozíció sikeresen létrehozva.',
            'data' => $created,
        ], Response::HTTP_CREATED);
    }

    public function update(int $id, UpdateRequest $request): JsonResponse
    {
        $companyId = (int) $request->validated('company_id');
        $position = $this->service->find($id, $companyId);
        $this->authorize(PositionPolicy::PERM_UPDATE, $position);

        $updated = $this->service->update($id, PositionData::from($request->validated()));

        return response()->json([
            'message' => 'Pozíció sikeresen frissítve.',
            'data' => $updated,
        ], Response::HTTP_OK);
    }

    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize(PositionPolicy::PERM_DELETE_ANY, Position::class);

        $deleted = $this->service->bulkDelete(
            $request->validated('ids'),
            (int) $request->validated('company_id')
        );

        return response()->json([
            'message' => 'Sikeres törlés.',
            'deleted' => $deleted,
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = (int) $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ])['company_id'];

        $position = $this->service->find($id, $companyId);
        $this->authorize(PositionPolicy::PERM_DELETE, $position);

        $deleted = $this->service->destroy($id, $companyId);

        return response()->json([
            'message' => $deleted ? 'Törlés sikeres.' : 'Törlés sikertelen.',
            'deleted' => (bool) $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function getToSelect(SelectorRequest $request): JsonResponse
    {
        return response()->json(
            $this->service->getToSelect(
                (int) $request->validated('company_id'),
                (bool) $request->boolean('only_active', true)
            ),
            Response::HTTP_OK
        );
    }
}
