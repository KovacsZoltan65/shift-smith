<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hq;

use App\Data\Company\CompanyIndexData;
use App\Data\Company\CompanyData;
use App\Data\Company\HqCompanyData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hq\HqCompanyIndexRequest;
use App\Http\Requests\Hq\HqCompanyStoreRequest;
use App\Http\Requests\Hq\HqCompanyUpdateRequest;
use App\Policies\HqCompanyPolicy;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

final class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $service
    ) {}

    public function index(HqCompanyIndexRequest $request): InertiaResponse
    {
        return Inertia::render('Hq/Companies/Index', [
            'title' => __('companies.hq.title'),
            'filter' => $request->validatedFilters(),
        ]);
    }

    public function fetch(HqCompanyIndexRequest $request): JsonResponse
    {
        $companies = $this->service->fetchHq($request);
        $items = CompanyIndexData::collect($companies->items());

        return response()->json([
            'message' => __('companies.hq.messages.fetch_success'),
            'data' => $items,
            'meta' => [
                'current_page' => $companies->currentPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
                'last_page' => $companies->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
    }

    public function getCompany(int $id): JsonResponse
    {
        $company = $this->service->findHq($id);

        return response()->json([
            'message' => __('companies.hq.messages.show_success'),
            'data' => HqCompanyData::fromModel($company),
        ], Response::HTTP_OK);
    }

    public function store(HqCompanyStoreRequest $request): JsonResponse
    {
        Gate::authorize(HqCompanyPolicy::PERM_CREATE);

        $validated = $request->validated();

        $created = $this->service->createInTenantGroup(
            (int) $validated['tenant_group_id'],
            CompanyData::from([
                'id' => null,
                'name' => (string) $validated['name'],
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'active' => (bool) ($validated['active'] ?? true),
                'created_at' => null,
            ]),
        );

        return response()->json([
            'message' => __('companies.hq.messages.created_success'),
            'data' => $created,
        ], Response::HTTP_CREATED);
    }

    public function update(int $id, HqCompanyUpdateRequest $request): JsonResponse
    {
        Gate::authorize(HqCompanyPolicy::PERM_UPDATE);

        $validated = $request->validated();

        $updated = $this->service->updateInTenantGroup(
            (int) $validated['tenant_group_id'],
            $id,
            CompanyData::from([
                'id' => $id,
                'name' => (string) $validated['name'],
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'active' => (bool) ($validated['active'] ?? true),
                'created_at' => null,
            ]),
        );

        return response()->json([
            'message' => __('companies.hq.messages.updated_success'),
            'data' => $updated,
        ], Response::HTTP_OK);
    }
}
