<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveType\DeleteLeaveTypeRequest;
use App\Http\Requests\LeaveType\FetchLeaveTypeRequest;
use App\Http\Requests\LeaveType\StoreLeaveTypeRequest;
use App\Http\Requests\LeaveType\UpdateLeaveTypeRequest;
use App\Models\LeaveType;
use App\Policies\LeaveTypePolicy;
use App\Services\Leave\LeaveTypeService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class LeaveTypeController extends Controller
{
    public function __construct(
        private readonly LeaveTypeService $service,
    ) {
    }

    public function index(FetchLeaveTypeRequest $request): InertiaResponse
    {
        $this->authorize(LeaveTypePolicy::PERM_VIEW_ANY, LeaveType::class);

        return Inertia::render('Admin/LeaveTypes/Index', [
            'filter' => $request->validatedFilters(),
            'companyId' => $request->currentCompanyId(),
        ]);
    }

    public function fetch(FetchLeaveTypeRequest $request): JsonResponse
    {
        $this->authorize(LeaveTypePolicy::PERM_VIEW_ANY, LeaveType::class);
        $payload = $this->service->fetch($request->currentCompanyId(), $request->validatedFilters());

        return response()->json([
            'message' => __('leave_types.messages.fetch_success'),
            'items' => $payload['items'],
            'meta' => $payload['meta'],
            'filter' => $payload['filters'],
            'options' => $payload['options'],
        ], Response::HTTP_OK);
    }

    public function selector(FetchLeaveTypeRequest $request): JsonResponse
    {
        $this->authorize(LeaveTypePolicy::PERM_VIEW_ANY, LeaveType::class);

        return response()->json([
            'message' => __('leave_types.messages.selector_success'),
            'data' => $this->service->selector($request->currentCompanyId(), $request->validatedFilters()),
        ], Response::HTTP_OK);
    }

    public function show(int $id, FetchLeaveTypeRequest $request): JsonResponse
    {
        $this->authorize(LeaveTypePolicy::PERM_VIEW, LeaveType::class);

        return response()->json([
            'message' => __('leave_types.messages.show_success'),
            'data' => $this->service->show($request->currentCompanyId(), $id),
        ], Response::HTTP_OK);
    }

    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        $this->authorize(LeaveTypePolicy::PERM_CREATE, LeaveType::class);

        return response()->json([
            'message' => __('leave_types.messages.created_success'),
            'data' => $this->service->store($request->currentCompanyId(), $request->validatedPayload()),
        ], Response::HTTP_CREATED);
    }

    public function update(int $id, UpdateLeaveTypeRequest $request): JsonResponse
    {
        $this->authorize(LeaveTypePolicy::PERM_UPDATE, LeaveType::class);

        return response()->json([
            'message' => __('leave_types.messages.updated_success'),
            'data' => $this->service->update($request->currentCompanyId(), $id, $request->validatedPayload()),
        ], Response::HTTP_OK);
    }

    public function destroy(int $id, DeleteLeaveTypeRequest $request): JsonResponse
    {
        $this->authorize(LeaveTypePolicy::PERM_DELETE, LeaveType::class);
        $this->service->destroy($request->currentCompanyId(), $id);

        return response()->json([
            'message' => __('leave_types.messages.deleted_success'),
            'deleted' => true,
        ], Response::HTTP_OK);
    }
}
