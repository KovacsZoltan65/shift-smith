<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MonthClosure\DeleteRequest;
use App\Http\Requests\MonthClosure\StoreRequest;
use App\Models\MonthClosure;
use App\Policies\MonthClosurePolicy;
use App\Services\MonthClosureService;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\JsonResponse;

class MonthClosureController extends Controller
{
    public function __construct(
        private readonly MonthClosureService $service
    ) {}

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize(MonthClosurePolicy::PERM_CREATE, MonthClosure::class);

        $closure = $this->service->close(
            companyId: $request->currentCompanyId(),
            actorUserId: (int) $request->user()->id,
            year: (int) $request->validated('year'),
            month: (int) $request->validated('month'),
            note: is_string($request->validated('note')) ? $request->validated('note') : null,
        );

        return response()->json([
            'message' => 'A hónap sikeresen lezárva.',
            'data' => [
                'id' => (int) $closure->id,
                'company_id' => (int) $closure->company_id,
                'year' => (int) $closure->year,
                'month' => (int) $closure->month,
                'closed_at' => $closure->closed_at?->format('Y-m-d H:i:s'),
                'closed_by_user_id' => $closure->closed_by_user_id !== null ? (int) $closure->closed_by_user_id : null,
                'closed_by_name' => $closure->closedBy?->name,
                'note' => $closure->note,
            ],
        ], Response::HTTP_CREATED);
    }

    public function destroy(DeleteRequest $request, int $id): JsonResponse
    {
        $companyId = $request->currentCompanyId();
        $closure = $this->service->findForCompany($companyId, $id);
        $this->authorize(MonthClosurePolicy::PERM_DELETE, $closure);

        $deleted = $this->service->reopen($companyId, $id);

        return response()->json([
            'message' => $deleted ? 'A hónap sikeresen újranyitva.' : 'Az újranyitás sikertelen.',
            'deleted' => $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
