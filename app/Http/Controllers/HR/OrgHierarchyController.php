<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrgHierarchy\DesignSettingsSaveRequest;
use App\Http\Requests\OrgHierarchy\EmployeeSearchRequest;
use App\Http\Requests\OrgHierarchy\GraphRequest;
use App\Http\Requests\OrgHierarchy\IntegrityRequest;
use App\Http\Requests\OrgHierarchy\MovePreviewRequest;
use App\Http\Requests\OrgHierarchy\MoveRequest;
use App\Http\Requests\OrgHierarchy\NodeRequest;
use App\Http\Requests\OrgHierarchy\PathRequest;
use App\Policies\OrgHierarchyPolicy;
use App\Services\CompanyContextService;
use App\Services\CurrentCompany;
use App\Services\Org\OrgHierarchyDesignSettingsService;
use App\Services\Org\OrgHierarchyGraphService;
use App\Services\Org\OrgHierarchyMutationService;
use App\Services\Org\OrgHierarchyPathService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class OrgHierarchyController extends Controller
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly CompanyContextService $companyContextService,
        private readonly OrgHierarchyDesignSettingsService $designSettingsService,
        private readonly OrgHierarchyGraphService $graphService,
        private readonly OrgHierarchyPathService $pathService,
        private readonly OrgHierarchyMutationService $mutationService,
    ) {
    }

    public function index(Request $request): InertiaResponse
    {
        $this->authorize(OrgHierarchyPolicy::PERM_VIEW_ANY);

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! is_int($currentCompanyId) || $currentCompanyId <= 0, 403, __('common.errors.no_company_selected'));

        $companies = $request->user() !== null
            ? $this->companyContextService->selectableCompanies($request->user())
            : [];

        return Inertia::render('HR/Hierarchy/Index', [
            'title' => 'Szervezeti hierarchia',
            'company_id' => $currentCompanyId,
            'companies' => $companies,
            'at_date' => now()->toDateString(),
            'ui_settings' => $this->designSettingsService->effectiveForUser(
                companyId: $currentCompanyId,
                userId: (int) $request->user()->id,
            ),
        ]);
    }

    public function graph(GraphRequest $request): JsonResponse
    {
        $this->authorize(OrgHierarchyPolicy::PERM_VIEW_ANY);
        $payload = $request->validatedPayload();

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! is_int($currentCompanyId) || $currentCompanyId <= 0, 403, __('common.errors.no_company_selected'));
        abort_if((int) $payload['company_id'] !== $currentCompanyId, 403, __('common.errors.company_scope_mismatch'));

        $graph = $this->graphService->getGraph(
            companyId: $currentCompanyId,
            rootEmployeeId: $payload['root_employee_id'],
            atDate: CarbonImmutable::parse($payload['at_date']),
            depth: (int) $payload['depth'],
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
        abort_if(! is_int($currentCompanyId) || $currentCompanyId <= 0, 403, __('common.errors.no_company_selected'));
        abort_if((int) $payload['company_id'] !== $currentCompanyId, 403, __('common.errors.company_scope_mismatch'));

        $node = $this->graphService->getNode(
            companyId: $currentCompanyId,
            employeeId: $id,
            atDate: CarbonImmutable::parse($payload['at_date']),
        );

        abort_if($node === null, 404, __('hierarchy.errors.node_not_found'));

        return response()->json([
            'message' => 'Node adatai sikeresen lekérve.',
            'data' => $node->toArray(),
        ], Response::HTTP_OK);
    }

    public function employeesSearch(EmployeeSearchRequest $request): JsonResponse
    {
        $this->authorize(OrgHierarchyPolicy::PERM_VIEW_ANY);
        $payload = $request->validatedPayload();

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! is_int($currentCompanyId) || $currentCompanyId <= 0, 403, __('common.errors.no_company_selected'));
        abort_if((int) $payload['company_id'] !== $currentCompanyId, 403, __('common.errors.company_scope_mismatch'));

        $rows = $this->graphService->searchEmployees(
            companyId: $currentCompanyId,
            query: $payload['q'],
            limit: (int) $payload['limit'],
        );

        return response()->json([
            'message' => 'Dolgozó keresés sikeres.',
            'data' => $rows,
        ], Response::HTTP_OK);
    }

    public function path(PathRequest $request): JsonResponse
    {
        $this->authorize(OrgHierarchyPolicy::PERM_VIEW_ANY);
        $payload = $request->validatedPayload();

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! is_int($currentCompanyId) || $currentCompanyId <= 0, 403, __('common.errors.no_company_selected'));
        abort_if((int) $payload['company_id'] !== $currentCompanyId, 403, __('common.errors.company_scope_mismatch'));

        try {
            $path = $this->pathService->getPath(
                companyId: $currentCompanyId,
                employeeId: (int) $payload['employee_id'],
                atDate: CarbonImmutable::parse($payload['at_date']),
            );
        } catch (ModelNotFoundException) {
            abort(404, __('hierarchy.errors.employee_not_found_in_current_company'));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Hierarchia útvonal sikeresen lekérve.',
            'data' => $path,
        ], Response::HTTP_OK);
    }

    public function saveDesignSettings(DesignSettingsSaveRequest $request): JsonResponse
    {
        $this->authorize(OrgHierarchyPolicy::PERM_VIEW_ANY);
        $payload = $request->validatedPayload();

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! is_int($currentCompanyId) || $currentCompanyId <= 0, 403, __('common.errors.no_company_selected'));
        abort_if((int) $payload['company_id'] !== $currentCompanyId, 403, __('common.errors.company_scope_mismatch'));

        $saved = $this->designSettingsService->saveForUser(
            companyId: $currentCompanyId,
            userId: (int) $request->user()->id,
            actorUserId: (int) $request->user()->id,
            settings: [
                'view_mode' => (string) $payload['view_mode'],
                'density' => (string) $payload['density'],
                'show_position' => (bool) $payload['show_position'],
            ],
        );

        return response()->json([
            'message' => 'Hierarchia UI beállítások mentve.',
            'data' => $saved,
        ], Response::HTTP_OK);
    }

    public function movePreview(MovePreviewRequest $request): JsonResponse
    {
        $this->authorize(OrgHierarchyPolicy::PERM_VIEW_ANY);
        $payload = $request->validatedPayload();

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! is_int($currentCompanyId) || $currentCompanyId <= 0, 403, __('common.errors.no_company_selected'));
        abort_if((int) $payload['company_id'] !== $currentCompanyId, 403, __('common.errors.company_scope_mismatch'));

        $preview = $this->mutationService->previewMove($payload);

        return response()->json([
            'message' => 'Hierarchia áthelyezés előnézet elkészült.',
            'data' => $preview,
        ], Response::HTTP_OK);
    }

    public function move(MoveRequest $request): JsonResponse
    {
        $this->authorize(OrgHierarchyPolicy::PERM_UPDATE);
        $payload = $request->validatedPayload();

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! is_int($currentCompanyId) || $currentCompanyId <= 0, 403, __('common.errors.no_company_selected'));
        abort_if((int) $payload['company_id'] !== $currentCompanyId, 403, __('common.errors.company_scope_mismatch'));

        $result = $this->mutationService->move($payload, (int) $request->user()->id);

        return response()->json([
            'message' => 'Hierarchia áthelyezés sikeres.',
            'data' => $result,
        ], Response::HTTP_OK);
    }

    public function integrity(IntegrityRequest $request): JsonResponse
    {
        $this->authorize(OrgHierarchyPolicy::PERM_VIEW_ANY);
        $payload = $request->validatedPayload();

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if(! is_int($currentCompanyId) || $currentCompanyId <= 0, 403, __('common.errors.no_company_selected'));
        abort_if((int) $payload['company_id'] !== $currentCompanyId, 403, __('common.errors.company_scope_mismatch'));

        $report = $this->mutationService->companyIntegrityReport(
            companyId: $currentCompanyId,
            atDate: CarbonImmutable::parse($payload['at_date'])->startOfDay(),
        );

        return response()->json([
            'message' => 'Hierarchia integritás riport elkészült.',
            'data' => $report,
        ], Response::HTTP_OK);
    }
}
