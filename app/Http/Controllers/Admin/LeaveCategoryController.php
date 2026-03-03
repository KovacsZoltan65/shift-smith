<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveCategory\DeleteLeaveCategoryRequest;
use App\Http\Requests\LeaveCategory\FetchLeaveCategoryRequest;
use App\Http\Requests\LeaveCategory\SelectorRequest;
use App\Http\Requests\LeaveCategory\StoreLeaveCategoryRequest;
use App\Http\Requests\LeaveCategory\UpdateLeaveCategoryRequest;
use App\Models\LeaveCategory;
use App\Policies\LeaveCategoryPolicy;
use App\Services\Leave\LeaveCategoryService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class LeaveCategoryController extends Controller
{
    public function __construct(
        private readonly LeaveCategoryService $service,
    ) {
    }

    public function index(FetchLeaveCategoryRequest $request): InertiaResponse
    {
        $this->authorize(LeaveCategoryPolicy::PERM_VIEW_ANY, LeaveCategory::class);

        return Inertia::render('Admin/LeaveCategories/Index', [
            'title' => 'Szabadsag kategoriak',
            'filter' => $request->validatedFilters(),
            'companyId' => $request->currentCompanyId(),
        ]);
    }

    public function fetch(FetchLeaveCategoryRequest $request): JsonResponse
    {
        $this->authorize(LeaveCategoryPolicy::PERM_VIEW_ANY, LeaveCategory::class);
        $payload = $this->service->fetch($request->currentCompanyId(), $request->validatedFilters());

        return response()->json([
            'message' => 'Szabadsag kategoriak sikeresen lekerve.',
            'items' => $payload['items'],
            'meta' => $payload['meta'],
            'filter' => $payload['filters'],
        ], Response::HTTP_OK);
    }

    public function selector(SelectorRequest $request): JsonResponse
    {
        $this->authorize(LeaveCategoryPolicy::PERM_VIEW_ANY, LeaveCategory::class);

        return response()->json([
            'data' => $this->service->selector($request->currentCompanyId(), $request->onlyActive()),
        ], Response::HTTP_OK);
    }

    public function show(int $id, FetchLeaveCategoryRequest $request): JsonResponse
    {
        $this->authorize(LeaveCategoryPolicy::PERM_VIEW_ANY, LeaveCategory::class);

        return response()->json([
            'message' => 'Szabadsag kategoria sikeresen lekerve.',
            'data' => $this->service->show($request->currentCompanyId(), $id),
        ], Response::HTTP_OK);
    }

    public function store(StoreLeaveCategoryRequest $request): JsonResponse
    {
        $this->authorize(LeaveCategoryPolicy::PERM_CREATE, LeaveCategory::class);

        return response()->json([
            'message' => 'Szabadsag kategoria sikeresen letrehozva.',
            'data' => $this->service->store($request->currentCompanyId(), $request->validatedPayload()),
        ], Response::HTTP_CREATED);
    }

    public function update(int $id, UpdateLeaveCategoryRequest $request): JsonResponse
    {
        $this->authorize(LeaveCategoryPolicy::PERM_UPDATE, LeaveCategory::class);

        return response()->json([
            'message' => 'Szabadsag kategoria sikeresen frissitve.',
            'data' => $this->service->update($request->currentCompanyId(), $id, $request->validatedPayload()),
        ], Response::HTTP_OK);
    }

    public function destroy(int $id, DeleteLeaveCategoryRequest $request): JsonResponse
    {
        $this->authorize(LeaveCategoryPolicy::PERM_DELETE, LeaveCategory::class);
        $this->service->destroy($request->currentCompanyId(), $id);

        return response()->json([
            'message' => 'Szabadsag kategoria sikeresen torolve.',
            'deleted' => true,
        ], Response::HTTP_OK);
    }
}
