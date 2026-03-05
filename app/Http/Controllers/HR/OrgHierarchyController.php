<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrgHierarchy\GraphRequest;
use App\Http\Requests\OrgHierarchy\NodeRequest;
use App\Policies\OrgHierarchyPolicy;
use App\Services\CompanyContextService;
use App\Services\CurrentCompany;
use App\Services\Org\OrgHierarchyGraphService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

final class OrgHierarchyController extends Controller
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly CompanyContextService $companyContextService,
        private readonly OrgHierarchyGraphService $graphService,
    ) {
    }

    public function index(Request $request): InertiaResponse
    {
        $this->authorize(OrgHierarchyPolicy::PERM_VIEW_ANY);

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! is_int($currentCompanyId) || $currentCompanyId <= 0, 403, 'No company selected');

        $companies = $request->user() !== null
            ? $this->companyContextService->selectableCompanies($request->user())
            : [];

        return Inertia::render('HR/Hierarchy/Index', [
            'title' => 'Szervezeti hierarchia',
            'company_id' => $currentCompanyId,
            'companies' => $companies,
            'at_date' => now()->toDateString(),
        ]);
    }

    public function graph(GraphRequest $request): JsonResponse
    {
        $this->authorize(OrgHierarchyPolicy::PERM_VIEW_ANY);
        $payload = $request->validatedPayload();

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! is_int($currentCompanyId) || $currentCompanyId <= 0, 403, 'No company selected');
        abort_if((int) $payload['company_id'] !== $currentCompanyId, 403, 'Company scope mismatch');

        $graph = $this->graphService->getGraph(
            companyId: $currentCompanyId,
            rootEmployeeId: $payload['root_employee_id'],
            atDate: CarbonImmutable::parse($payload['at_date']),
        );

        return response()->json([
            'message' => 'Szervezeti graf sikeresen lekérve.',
            'data' => $graph->toArray(),
        ], Response::HTTP_OK);
    }

    public function node(NodeRequest $request, int $id): JsonResponse
    {
        $this->authorize(OrgHierarchyPolicy::PERM_VIEW_ANY);
        $payload = $request->validatedPayload();

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! is_int($currentCompanyId) || $currentCompanyId <= 0, 403, 'No company selected');
        abort_if((int) $payload['company_id'] !== $currentCompanyId, 403, 'Company scope mismatch');

        $node = $this->graphService->getNode(
            companyId: $currentCompanyId,
            employeeId: $id,
            atDate: CarbonImmutable::parse($payload['at_date']),
        );

        abort_if($node === null, 404, 'Node not found');

        return response()->json([
            'message' => 'Node adatai sikeresen lekérve.',
            'data' => $node->toArray(),
        ], Response::HTTP_OK);
    }
}
