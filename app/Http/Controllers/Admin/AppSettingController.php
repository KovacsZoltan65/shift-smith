<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppSetting\BulkDeleteRequest;
use App\Http\Requests\AppSetting\DeleteRequest;
use App\Http\Requests\AppSetting\FetchRequest;
use App\Http\Requests\AppSetting\StoreRequest;
use App\Http\Requests\AppSetting\UpdateRequest;
use App\Models\AppSetting;
use App\Policies\AppSettingPolicy;
use App\Services\AppSettingService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class AppSettingController extends Controller
{
    public function __construct(
        private readonly AppSettingService $service,
    ) {
    }

    public function index(FetchRequest $request): InertiaResponse
    {
        $this->authorize(AppSettingPolicy::PERM_VIEW_ANY, AppSetting::class);

        return Inertia::render('Admin/AppSettings/Index', [
            'title' => 'App Settings',
            'filter' => $request->validatedFilters(),
        ]);
    }

    public function fetch(FetchRequest $request): JsonResponse
    {
        $this->authorize(AppSettingPolicy::PERM_VIEW_ANY, AppSetting::class);

        $payload = $this->service->fetch($request->validatedFilters());

        return response()->json([
            'message' => 'App settings sikeresen lekérve.',
            'items' => $payload['items'],
            'meta' => $payload['meta'],
            'filter' => $payload['filters'],
            'options' => $payload['options'],
        ], Response::HTTP_OK);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorize(AppSettingPolicy::PERM_VIEW, AppSetting::class);

        return response()->json([
            'message' => 'App setting sikeresen lekérve.',
            'data' => $this->service->show($id),
        ], Response::HTTP_OK);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize(AppSettingPolicy::PERM_CREATE, AppSetting::class);

        return response()->json([
            'message' => 'App setting sikeresen létrehozva.',
            'data' => $this->service->store($request->validatedPayload()),
        ], Response::HTTP_CREATED);
    }

    public function update(int $id, UpdateRequest $request): JsonResponse
    {
        $this->authorize(AppSettingPolicy::PERM_UPDATE, AppSetting::class);

        return response()->json([
            'message' => 'App setting sikeresen frissítve.',
            'data' => $this->service->update($id, $request->validatedPayload()),
        ], Response::HTTP_OK);
    }

    public function destroy(int $id, DeleteRequest $request): JsonResponse
    {
        $this->authorize(AppSettingPolicy::PERM_DELETE, AppSetting::class);

        $deleted = $this->service->destroy($id);

        return response()->json([
            'message' => $deleted ? 'App setting sikeresen törölve.' : 'A törlés sikertelen.',
            'deleted' => $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function bulkDestroy(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize(AppSettingPolicy::PERM_DELETE_ANY, AppSetting::class);

        return response()->json([
            'message' => 'Bulk törlés sikeres.',
            'deleted' => $this->service->bulkDestroy($request->validated('ids')),
        ], Response::HTTP_OK);
    }
}
