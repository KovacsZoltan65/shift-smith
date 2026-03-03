<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SickLeaveCategory\SelectorRequest;
use App\Models\SickLeaveCategory;
use App\Policies\SickLeaveCategoryPolicy;
use App\Services\SickLeaveCategoryService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SickLeaveCategoryController extends Controller
{
    public function __construct(
        private readonly SickLeaveCategoryService $service,
    ) {
    }

    public function selector(SelectorRequest $request): JsonResponse
    {
        $this->authorize(SickLeaveCategoryPolicy::PERM_VIEW_ANY, SickLeaveCategory::class);

        return response()->json([
            'data' => $this->service->selector($request->currentCompanyId(), $request->onlyActive()),
        ], Response::HTTP_OK);
    }
}
