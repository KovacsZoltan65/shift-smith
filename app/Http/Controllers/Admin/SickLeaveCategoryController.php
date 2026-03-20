<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SickLeaveCategory\DeleteSickLeaveCategoryRequest;
use App\Http\Requests\SickLeaveCategory\FetchSickLeaveCategoryRequest;
use App\Http\Requests\SickLeaveCategory\SelectorRequest;
use App\Http\Requests\SickLeaveCategory\StoreSickLeaveCategoryRequest;
use App\Http\Requests\SickLeaveCategory\UpdateSickLeaveCategoryRequest;
use App\Models\SickLeaveCategory;
use App\Policies\SickLeaveCategoryPolicy;
use App\Services\Leave\SickLeaveCategoryService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class SickLeaveCategoryController extends Controller
{
    public function __construct(
        private readonly SickLeaveCategoryService $service,
    ) {
    }

    public function index(FetchSickLeaveCategoryRequest $request): InertiaResponse
    {
        $this->authorize(SickLeaveCategoryPolicy::PERM_VIEW_ANY, SickLeaveCategory::class);

        return Inertia::render('Admin/SickLeaveCategories/Index', [
            'filter' => $request->validatedFilters(),
            'companyId' => $request->currentCompanyId(),
        ]);
    }

    public function fetch(FetchSickLeaveCategoryRequest $request): JsonResponse
    {
        $this->authorize(SickLeaveCategoryPolicy::PERM_VIEW_ANY, SickLeaveCategory::class);
        $payload = $this->service->fetch($request->currentCompanyId(), $request->validatedFilters());

        return response()->json([
            'message' => __('sick_leave_categories.messages.fetch_success'),
            'items' => $payload['items'],
            'meta' => $payload['meta'],
            'filter' => $payload['filters'],
        ], Response::HTTP_OK);
    }

    public function selector(SelectorRequest $request): JsonResponse
    {
        $this->authorize(SickLeaveCategoryPolicy::PERM_VIEW_ANY, SickLeaveCategory::class);

        return response()->json([
            'data' => $this->service->selector($request->currentCompanyId(), $request->onlyActive()),
        ], Response::HTTP_OK);
    }

    public function show(int $id, FetchSickLeaveCategoryRequest $request): JsonResponse
    {
        $this->authorize(SickLeaveCategoryPolicy::PERM_VIEW_ANY, SickLeaveCategory::class);

        return response()->json([
            'message' => __('sick_leave_categories.messages.show_success'),
            'data' => $this->service->show($request->currentCompanyId(), $id),
        ], Response::HTTP_OK);
    }

    public function store(StoreSickLeaveCategoryRequest $request): JsonResponse
    {
        $this->authorize(SickLeaveCategoryPolicy::PERM_CREATE, SickLeaveCategory::class);

        return response()->json([
            'message' => __('sick_leave_categories.messages.created_success'),
            'data' => $this->service->store($request->currentCompanyId(), $request->validatedPayload()),
        ], Response::HTTP_CREATED);
    }

    public function update(int $id, UpdateSickLeaveCategoryRequest $request): JsonResponse
    {
        $this->authorize(SickLeaveCategoryPolicy::PERM_UPDATE, SickLeaveCategory::class);

        return response()->json([
            'message' => __('sick_leave_categories.messages.updated_success'),
            'data' => $this->service->update($request->currentCompanyId(), $id, $request->validatedPayload()),
        ], Response::HTTP_OK);
    }

    public function destroy(int $id, DeleteSickLeaveCategoryRequest $request): JsonResponse
    {
        $this->authorize(SickLeaveCategoryPolicy::PERM_DELETE, SickLeaveCategory::class);
        $this->service->destroy($request->currentCompanyId(), $id);

        return response()->json([
            'message' => __('sick_leave_categories.messages.deleted_success'),
            'deleted' => true,
        ], Response::HTTP_OK);
    }
}
