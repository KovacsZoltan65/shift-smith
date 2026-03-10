<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserSetting\BulkDeleteRequest;
use App\Http\Requests\UserSetting\DeleteRequest;
use App\Http\Requests\UserSetting\FetchRequest;
use App\Http\Requests\UserSetting\StoreRequest;
use App\Http\Requests\UserSetting\UpdateRequest;
use App\Models\UserSetting;
use App\Policies\UserSettingPolicy;
use App\Services\UserSettingService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class UserSettingController extends Controller
{
    public function __construct(
        private readonly UserSettingService $service,
    ) {
    }

    public function index(FetchRequest $request): InertiaResponse
    {
        $this->authorize(UserSettingPolicy::PERM_VIEW_ANY, UserSetting::class);

        return Inertia::render('Admin/UserSettings/Index', [
            'title' => __('user_settings.title'),
            'filter' => $request->validatedFilters(),
            'companyId' => $request->currentCompanyId(),
            'targetUserId' => $request->targetUserId(),
        ]);
    }

    public function fetch(FetchRequest $request): JsonResponse
    {
        $this->authorize(UserSettingPolicy::PERM_VIEW_ANY, UserSetting::class);
        $companyId = $request->currentCompanyId();
        $userId = $request->targetUserId();

        $payload = $this->service->fetch($companyId, $userId, $request->validatedFilters());

        return response()->json([
            'message' => __('user_settings.messages.fetch_success'),
            'items' => $payload['items'],
            'meta' => $payload['meta'],
            'filter' => $payload['filters'],
            'options' => $payload['options'],
        ], Response::HTTP_OK);
    }

    public function show(int $id, FetchRequest $request): JsonResponse
    {
        $this->authorize(UserSettingPolicy::PERM_VIEW, UserSetting::class);

        return response()->json([
            'message' => __('user_settings.messages.show_success'),
            'data' => $this->service->show($request->currentCompanyId(), $request->targetUserId(), $id),
        ], Response::HTTP_OK);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize(UserSettingPolicy::PERM_CREATE, UserSetting::class);

        return response()->json([
            'message' => __('user_settings.messages.created_success'),
            'data' => $this->service->store($request->currentCompanyId(), $request->targetUserId(), $request->validatedPayload()),
        ], Response::HTTP_CREATED);
    }

    public function update(int $id, UpdateRequest $request): JsonResponse
    {
        $this->authorize(UserSettingPolicy::PERM_UPDATE, UserSetting::class);

        return response()->json([
            'message' => __('user_settings.messages.updated_success'),
            'data' => $this->service->update($request->currentCompanyId(), $request->targetUserId(), $id, $request->validatedPayload()),
        ], Response::HTTP_OK);
    }

    public function destroy(int $id, DeleteRequest $request): JsonResponse
    {
        $this->authorize(UserSettingPolicy::PERM_DELETE, UserSetting::class);
        $deleted = $this->service->destroy($request->currentCompanyId(), $request->targetUserId(), $id);

        return response()->json([
            'message' => $deleted ? __('user_settings.messages.deleted_success') : __('user_settings.messages.delete_failed'),
            'deleted' => $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function bulkDestroy(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize(UserSettingPolicy::PERM_DELETE_ANY, UserSetting::class);

        return response()->json([
            'message' => __('user_settings.messages.bulk_deleted_success'),
            'deleted' => $this->service->bulkDestroy($request->currentCompanyId(), $request->targetUserId(), $request->validated('ids')),
        ], Response::HTTP_OK);
    }
}
