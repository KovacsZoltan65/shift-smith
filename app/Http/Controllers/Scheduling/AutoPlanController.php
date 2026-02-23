<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scheduling;

use App\Data\Scheduling\AutoPlan\GenerateInputData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\AutoPlan\GenerateRequest;
use App\Models\WorkSchedule;
use App\Policies\WorkSchedulePolicy;
use App\Services\Scheduling\AutoPlanService;
use App\Support\CurrentCompanyContext;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AutoPlanController extends Controller
{
    public function __construct(
        private readonly AutoPlanService $service,
        private readonly CurrentCompanyContext $companyContext
    ) {}

    public function defaults(): JsonResponse
    {
        $this->authorize(WorkSchedulePolicy::PERM_AUTOPLAN, WorkSchedule::class);

        return response()->json([
            'message' => 'AutoPlan alapbeállítások sikeresen lekérve.',
            'data' => $this->service->defaults(),
        ], Response::HTTP_OK);
    }

    public function generate(GenerateRequest $request): JsonResponse
    {
        $companyId = $this->companyContext->resolve($request);
        $userId = (int) $request->user()->id;

        $data = GenerateInputData::fromPayload($request->validated());
        $result = $this->service->generate($companyId, $userId, $data);

        return response()->json([
            'message' => 'AutoPlan draft generálás kész.',
            'data' => $result,
        ], Response::HTTP_CREATED);
    }
}
