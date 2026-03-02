<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\UpdateEmployeeLeaveProfileRequest;
use App\Services\EmployeeService;
use App\Services\Employees\EmployeeLeaveProfileService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class EmployeeLeaveProfileController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employees,
        private readonly EmployeeLeaveProfileService $profiles,
    ) {
    }

    public function show(int $id): JsonResponse
    {
        $employee = $this->employees->getEmployee($id);
        $this->authorize('update', $employee);

        try {
            $profile = $this->profiles->show($id);
        } catch (DomainException) {
            abort(Response::HTTP_NOT_FOUND, 'Dolgozó szabadság profil nem érhető el ebben a cég kontextusban.');
        }

        return response()->json([
            'message' => 'Dolgozó szabadság profil sikeresen lekérve.',
            'data' => $profile->toArray(),
        ], Response::HTTP_OK);
    }

    public function update(UpdateEmployeeLeaveProfileRequest $request, int $id): JsonResponse
    {
        $employee = $this->employees->getEmployee($id);
        $this->authorize('update', $employee);

        try {
            $profile = $this->profiles->update($id, $request->validated());
        } catch (DomainException $exception) {
            if (str_contains($exception->getMessage(), 'current company scope')) {
                abort(Response::HTTP_NOT_FOUND, 'Dolgozó szabadság profil nem érhető el ebben a cég kontextusban.');
            }

            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Dolgozó szabadság profil sikeresen mentve.',
            'data' => $profile->toArray(),
        ], Response::HTTP_OK);
    }
}
