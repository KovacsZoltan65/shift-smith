<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hq;

use App\Data\Company\CompanyData;
use App\Data\Company\CompanyIndexData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hq\HqCompanyIndexRequest;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
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
        $company = $this->service->find($id);

        return response()->json([
            'message' => __('companies.hq.messages.show_success'),
            'data' => CompanyData::fromModel($company),
        ], Response::HTTP_OK);
    }
}
