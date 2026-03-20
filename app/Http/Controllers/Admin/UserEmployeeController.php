<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserEmployee\DestroyRequest;
use App\Http\Requests\UserEmployee\StoreRequest;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserEmployee;
use App\Policies\UserEmployeePolicy;
use App\Services\UserEmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

final class UserEmployeeController extends Controller
{
    public function __construct(
        private readonly UserEmployeeService $service,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize(UserEmployeePolicy::PERM_VIEW_ANY, UserEmployee::class);

        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('Admin/UserEmployees/Index', [
            ...$this->service->indexPayload($actor),
        ]);
    }

    public function fetch(Request $request, User $user): JsonResponse
    {
        $this->authorize(UserEmployeePolicy::PERM_VIEW_ANY, UserEmployee::class);

        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'message' => __('user_employees.messages.fetch_success'),
            'data' => $this->service->fetchPayload($actor, $user),
        ], Response::HTTP_OK);
    }

    public function store(StoreRequest $request, User $user): JsonResponse
    {
        $this->authorize(UserEmployeePolicy::PERM_CREATE, UserEmployee::class);

        /** @var User $actor */
        $actor = $request->user();

        $employee = Employee::query()->findOrFail((int) $request->validated('employee_id'));
        $this->service->attach($actor, $user, $employee);

        return response()->json([
            'message' => __('user_employees.messages.store_success'),
            'data' => $this->service->fetchPayload($actor, $user),
        ], Response::HTTP_OK);
    }

    public function destroy(DestroyRequest $request, User $user, Employee $employee): JsonResponse
    {
        $this->authorize(UserEmployeePolicy::PERM_DELETE, UserEmployee::class);

        /** @var User $actor */
        $actor = $request->user();

        $this->service->detach($actor, $user, $employee);

        return response()->json([
            'message' => __('user_employees.messages.destroy_success'),
            'data' => $this->service->fetchPayload($actor, $user),
        ], Response::HTTP_OK);
    }
}
