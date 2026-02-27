<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateRoleRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\Admin\UserRoleService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class UserRoleController extends Controller
{
    public function __construct(
        private readonly UserRoleService $service,
    ) {}

    public function update(UpdateRoleRequest $request, User $user): JsonResponse
    {
        $updatedUser = $this->service->setPrimaryRole($user, (int) $request->validated('role_id'));

        return response()->json([
            'message' => 'A felhasználó szerepköre sikeresen frissítve.',
            'data' => [
                'id' => (int) $updatedUser->id,
                'primary_role_name' => $updatedUser->roles->first()?->name,
                'roles' => $updatedUser->roles
                    ->map(fn ($role): array => [
                        'id' => (int) $role->id,
                        'name' => (string) $role->name,
                    ])
                    ->values()
                    ->all(),
            ],
        ], Response::HTTP_OK);
    }
}
