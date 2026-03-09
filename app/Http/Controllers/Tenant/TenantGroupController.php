<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\Tenant\TenantGroupData;
use App\Data\Tenant\TenantGroupListData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\TenantGroupFetchRequest;
use App\Http\Requests\Tenant\TenantGroupStoreRequest;
use App\Http\Requests\Tenant\TenantGroupUpdateRequest;
use App\Http\Resources\Tenant\TenantGroupResource;
use App\Models\TenantGroup;
use App\Services\Tenant\TenantGroupDeletionBlockedException;
use App\Services\Tenant\TenantGroupService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * HQ controller a landlord oldali TenantGroup CRUD végpontokhoz.
 *
 * A controller itt is vékony marad: authorization, request validáció és válaszformázás történik benne.
 * Minden lekérdezési és mutációs logika a service és repository rétegben marad.
 */
final class TenantGroupController extends Controller
{
    public function __construct(
        private readonly TenantGroupService $service,
    ) {}

    /**
     * Kirendereli a landlord kezelőoldalt; a tényleges listaadatok aszinkron fetch hívásból érkeznek.
     */
    public function index(TenantGroupFetchRequest $request): InertiaResponse
    {
        $this->authorize('viewAny', TenantGroup::class);

        return Inertia::render('TenantGroups/Index', [
            'title' => __('tenant_groups.title'),
            'filter' => $request->validatedFilters(),
        ]);
    }

    /**
     * Visszaadja a HQ TenantGroup listaoldal által használt datatable payloadot.
     */
    public function fetch(TenantGroupFetchRequest $request): JsonResponse
    {
        $this->authorize('viewAny', TenantGroup::class);

        $tenantGroups = $this->service->fetch($request->validatedFilters());

        return response()->json([
            'message' => __('tenant_groups.fetch_success'),
            'data' => TenantGroupListData::collect($tenantGroups->items()),
            'meta' => [
                'current_page' => $tenantGroups->currentPage(),
                'per_page' => $tenantGroups->perPage(),
                'total' => $tenantGroups->total(),
                'last_page' => $tenantGroups->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
    }

    public function show(TenantGroup $tenantGroup): JsonResponse
    {
        $this->authorize('view', $tenantGroup);

        return response()->json([
            'message' => __('tenant_groups.show_success'),
            'data' => TenantGroupResource::make($tenantGroup),
        ], Response::HTTP_OK);
    }

    public function store(TenantGroupStoreRequest $request): JsonResponse
    {
        $this->authorize('create', TenantGroup::class);

        $tenantGroup = $this->service->store(TenantGroupData::from([
            'id' => null,
            ...$request->validated(),
            'databaseName' => null,
            'createdAt' => null,
            'updatedAt' => null,
            'deletedAt' => null,
        ]));

        return response()->json([
            'message' => __('tenant_groups.created_successfully'),
            'data' => TenantGroupResource::make($tenantGroup),
        ], Response::HTTP_CREATED);
    }

    public function update(TenantGroupUpdateRequest $request, TenantGroup $tenantGroup): JsonResponse
    {
        $this->authorize('update', $tenantGroup);

        $updated = $this->service->update($tenantGroup, TenantGroupData::from([
            'id' => (int) $tenantGroup->id,
            ...$request->validated(),
            'databaseName' => $tenantGroup->database_name,
            'createdAt' => $tenantGroup->created_at?->toDateTimeString(),
            'updatedAt' => $tenantGroup->updated_at?->toDateTimeString(),
            'deletedAt' => $tenantGroup->deleted_at?->toDateTimeString(),
        ]));

        return response()->json([
            'message' => __('tenant_groups.updated_successfully'),
            'data' => TenantGroupResource::make($updated),
        ], Response::HTTP_OK);
    }

    /**
     * Strukturált 409 választ ad vissza, ha a kapcsolódó cégek még blokkolják az archiválást.
     */
    public function destroy(TenantGroup $tenantGroup): JsonResponse
    {
        $this->authorize('delete', $tenantGroup);

        try {
            $this->service->destroy($tenantGroup);
        } catch (TenantGroupDeletionBlockedException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'tenant_group' => [$exception->getMessage()],
                ],
                'meta' => [
                    'impact' => $exception->impact,
                ],
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'message' => __('tenant_groups.archived_successfully'),
            'deleted' => true,
        ], Response::HTTP_OK);
    }
}
