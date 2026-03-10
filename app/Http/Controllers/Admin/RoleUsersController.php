<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\UpdateUsersRequest;
use App\Models\Admin\Role;
use App\Policies\RolePolicy;
use App\Services\Admin\RoleUsersService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class RoleUsersController extends Controller
{
    public function __construct(
        private readonly RoleUsersService $service,
    ) {}

    public function update(UpdateUsersRequest $request, Role $role): JsonResponse
    {
        $updatedRole = $this->service->syncUsers($role, $request->validated('user_ids', []));

        return response()->json([
            'message' => __('roles.messages.users_updated_success'),
            'data' => [
                'id' => (int) $updatedRole->id,
                'users_count' => (int) $updatedRole->users_count,
                'user_ids' => $updatedRole->users->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            ],
        ], Response::HTTP_OK);
    }
}
