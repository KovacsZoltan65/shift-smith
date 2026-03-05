<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Absence\DeleteAbsenceRequest;
use App\Http\Requests\Absence\FetchAbsenceRequest;
use App\Http\Requests\Absence\ShowAbsenceRequest;
use App\Http\Requests\Absence\StoreAbsenceRequest;
use App\Http\Requests\Absence\UpdateAbsenceRequest;
use App\Models\EmployeeAbsence;
use App\Policies\EmployeeAbsencePolicy;
use App\Services\AbsenceService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AbsenceController extends Controller
{
    public function __construct(
        private readonly AbsenceService $service,
    ) {
    }

    public function fetch(FetchAbsenceRequest $request): JsonResponse
    {
        $this->authorize(EmployeeAbsencePolicy::PERM_VIEW_ANY, EmployeeAbsence::class);

        return response()->json([
            'message' => 'Tavollet esemenyek sikeresen lekerve.',
            'data' => $this->service->fetchCalendarEvents($request->currentCompanyId(), $request->validated()),
        ], Response::HTTP_OK);
    }

    public function store(StoreAbsenceRequest $request): JsonResponse
    {
        $this->authorize(EmployeeAbsencePolicy::PERM_CREATE, EmployeeAbsence::class);
        $rows = $this->service->store($request->currentCompanyId(), (int) $request->user()->id, $request->validated());

        return response()->json([
            'message' => 'Tavolletek sikeresen letrehozva.',
            'data' => $rows,
            'count' => count($rows),
        ], Response::HTTP_CREATED);
    }

    public function show(int $id, ShowAbsenceRequest $request): JsonResponse
    {
        $absence = $this->service->getModel($request->currentCompanyId(), $id);
        $this->authorize('view', $absence);

        return response()->json([
            'message' => 'Tavollet sikeresen lekerve.',
            'data' => $this->service->show($request->currentCompanyId(), $id),
        ], Response::HTTP_OK);
    }

    public function update(int $id, UpdateAbsenceRequest $request): JsonResponse
    {
        $absence = $this->service->getModel($request->currentCompanyId(), $id);
        $this->authorize('update', $absence);

        return response()->json([
            'message' => 'Tavollet sikeresen frissitve.',
            'data' => $this->service->update($request->currentCompanyId(), $id, (int) $request->user()->id, $request->validated()),
        ], Response::HTTP_OK);
    }

    public function destroy(int $id, DeleteAbsenceRequest $request): JsonResponse
    {
        $absence = $this->service->getModel($request->currentCompanyId(), $id);
        $this->authorize('delete', $absence);
        $this->service->destroy($request->currentCompanyId(), $id);

        return response()->json([
            'message' => 'Tavollet sikeresen torolve.',
            'deleted' => true,
        ], Response::HTTP_OK);
    }
}
