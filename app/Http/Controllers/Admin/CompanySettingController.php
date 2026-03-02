<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanySetting\BulkDeleteRequest;
use App\Http\Requests\CompanySetting\DeleteRequest;
use App\Http\Requests\CompanySetting\EffectiveRequest;
use App\Http\Requests\CompanySetting\FetchRequest;
use App\Http\Requests\CompanySetting\StoreRequest;
use App\Http\Requests\CompanySetting\UpdateRequest;
use App\Models\CompanySetting;
use App\Policies\CompanySettingPolicy;
use App\Services\CompanySettingService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class CompanySettingController extends Controller
{
    public function __construct(
        private readonly CompanySettingService $service,
    ) {
    }

    public function index(FetchRequest $request): InertiaResponse
    {
        $this->authorize(CompanySettingPolicy::PERM_VIEW_ANY, CompanySetting::class);

        return Inertia::render('Admin/CompanySettings/Index', [
            'title' => 'Company Settings',
            'filter' => $request->validatedFilters(),
            'companyId' => $request->currentCompanyId(),
        ]);
    }

    public function fetch(FetchRequest $request): JsonResponse
    {
        $this->authorize(CompanySettingPolicy::PERM_VIEW_ANY, CompanySetting::class);
        $companyId = $request->currentCompanyId();
        $payload = $this->service->fetch($companyId, $request->validatedFilters());

        return response()->json([
            'message' => 'Company settings sikeresen lekérve.',
            'items' => $payload['items'],
            'meta' => $payload['meta'],
            'filter' => $payload['filters'],
            'options' => $payload['options'],
        ], Response::HTTP_OK);
    }

    public function show(int $id, FetchRequest $request): JsonResponse
    {
        $this->authorize(CompanySettingPolicy::PERM_VIEW, CompanySetting::class);

        return response()->json([
            'message' => 'Company setting sikeresen lekérve.',
            'data' => $this->service->show($request->currentCompanyId(), $id),
        ], Response::HTTP_OK);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize(CompanySettingPolicy::PERM_CREATE, CompanySetting::class);

        return response()->json([
            'message' => 'Company setting sikeresen létrehozva.',
            'data' => $this->service->store($request->currentCompanyId(), $request->validatedPayload()),
        ], Response::HTTP_CREATED);
    }

    public function update(int $id, UpdateRequest $request): JsonResponse
    {
        $this->authorize(CompanySettingPolicy::PERM_UPDATE, CompanySetting::class);

        return response()->json([
            'message' => 'Company setting sikeresen frissítve.',
            'data' => $this->service->update($request->currentCompanyId(), $id, $request->validatedPayload()),
        ], Response::HTTP_OK);
    }

    public function destroy(int $id, DeleteRequest $request): JsonResponse
    {
        $this->authorize(CompanySettingPolicy::PERM_DELETE, CompanySetting::class);
        $deleted = $this->service->destroy($request->currentCompanyId(), $id);

        return response()->json([
            'message' => $deleted ? 'Company setting sikeresen törölve.' : 'A törlés sikertelen.',
            'deleted' => $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function bulkDestroy(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize(CompanySettingPolicy::PERM_DELETE_ANY, CompanySetting::class);

        return response()->json([
            'message' => 'Bulk törlés sikeres.',
            'deleted' => $this->service->bulkDestroy($request->currentCompanyId(), $request->validated('ids')),
        ], Response::HTTP_OK);
    }

    public function effective(EffectiveRequest $request): JsonResponse
    {
        $this->authorize(CompanySettingPolicy::PERM_VIEW_ANY, CompanySetting::class);

        $companyId = $request->currentCompanyId();
        $keys = is_array($request->validated('keys')) ? $request->validated('keys') : [];
        $group = $request->validated('group');

        if (($keys === [] || $keys === null) && is_string($group) && $group !== '') {
            $fetched = $this->service->fetch($companyId, ['group' => $group, 'perPage' => 100]);
            $keys = array_map(static fn ($item): string => (string) $item->key, $fetched['items']);
        }

        return response()->json([
            'message' => 'Effective settings sikeresen lekérve.',
            'data' => $this->service->effective(
                $companyId,
                $keys,
                is_numeric($request->validated('user_id')) ? (int) $request->validated('user_id') : null
            ),
        ], Response::HTTP_OK);
    }
}
