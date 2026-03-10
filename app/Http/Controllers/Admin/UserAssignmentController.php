<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserAssignments\AssignEmployeeRequest;
use App\Http\Requests\UserAssignments\AttachCompanyRequest;
use App\Http\Requests\UserAssignments\DetachCompanyRequest;
use App\Http\Requests\UserAssignments\RemoveEmployeeRequest;
use App\Models\Company;
use App\Models\User;
use App\Policies\UserAssignmentPolicy;
use App\Services\UserAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

final class UserAssignmentController extends Controller
{
    public function __construct(
        private readonly UserAssignmentService $service,
    ) {}

    public function index(): InertiaResponse
    {
        $this->authorize(UserAssignmentPolicy::PERM_VIEW_ANY);

        return Inertia::render('Admin/UserAssignments/Index', $this->service->indexPayload());
    }

    public function fetchUsers(Request $request): JsonResponse
    {
        $this->authorize(UserAssignmentPolicy::PERM_VIEW_ANY);

        $payload = $this->service->fetchUsers(
            search: trim($request->string('q')->toString()),
            perPage: $request->integer('per_page', 15),
        );

        return response()->json([
            'message' => __('user_assignments.messages.users_fetch_success'),
            'data' => $payload['items'],
            'meta' => $payload['meta'],
        ], Response::HTTP_OK);
    }

    public function fetch(Request $request, User $user): JsonResponse
    {
        $this->authorize(UserAssignmentPolicy::PERM_VIEW_ANY);

        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'message' => __('user_assignments.messages.fetch_success'),
            'data' => $this->service->fetchUserAssignments($actor, $user),
        ], Response::HTTP_OK);
    }

    public function attachCompany(AttachCompanyRequest $request, User $user): JsonResponse
    {
        $this->authorize(UserAssignmentPolicy::PERM_UPDATE);

        /** @var User $actor */
        $actor = $request->user();

        $this->service->attachCompany($actor, $user, (int) $request->validated('company_id'));

        return response()->json([
            'message' => __('user_assignments.messages.company_attach_success'),
            'data' => $this->service->fetchUserAssignments($actor, $user, false),
        ], Response::HTTP_OK);
    }

    public function detachCompany(DetachCompanyRequest $request, User $user, Company $company): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->service->detachCompany($actor, $user, $company);

        return response()->json([
            'message' => __('user_assignments.messages.company_detach_success'),
            'data' => $this->service->fetchUserAssignments($actor, $user, false),
        ], Response::HTTP_OK);
    }

    public function assignEmployee(AssignEmployeeRequest $request, User $user, Company $company): JsonResponse
    {
        $this->authorize(UserAssignmentPolicy::PERM_UPDATE);

        /** @var User $actor */
        $actor = $request->user();

        $this->service->assignEmployee($actor, $user, $company, (int) $request->validated('employee_id'));

        return response()->json([
            'message' => __('user_assignments.messages.employee_assign_success'),
            'data' => $this->service->fetchUserAssignments($actor, $user, false),
        ], Response::HTTP_OK);
    }

    public function removeEmployee(RemoveEmployeeRequest $request, User $user, Company $company): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->service->removeEmployee($actor, $user, $company);

        return response()->json([
            'message' => __('user_assignments.messages.employee_remove_success'),
            'data' => $this->service->fetchUserAssignments($actor, $user, false),
        ], Response::HTTP_OK);
    }
}
