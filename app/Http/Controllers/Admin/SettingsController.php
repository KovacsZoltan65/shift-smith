<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Data\Settings\SettingSaveValueData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FetchRequest;
use App\Http\Requests\Settings\SaveRequest;
use App\Models\AppSetting;
use App\Policies\AppSettingPolicy;
use App\Services\CurrentCompany;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $service,
        private readonly CurrentCompany $currentCompany
    ) {}

    public function app(Request $request): InertiaResponse
    {
        $this->authorize(AppSettingPolicy::ABILITY_VIEW_APP, AppSetting::class);

        return $this->renderIndex($request, 'app');
    }

    public function company(Request $request): InertiaResponse
    {
        $this->authorize(AppSettingPolicy::ABILITY_VIEW_COMPANY, AppSetting::class);

        return $this->renderIndex($request, 'company');
    }

    public function user(Request $request): InertiaResponse
    {
        $this->authorize(AppSettingPolicy::ABILITY_VIEW_USER, AppSetting::class);

        return $this->renderIndex($request, 'user');
    }

    private function renderIndex(Request $request, string $initialLevel): InertiaResponse
    {
        return Inertia::render('Admin/Settings/Index', [
            'title' => 'Beállítások',
            'initialLevel' => $initialLevel,
            'current_company_id' => $this->currentCompany->currentCompanyId($request),
        ]);
    }

    public function fetch(FetchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $data = $this->service->fetch([
            'level' => (string) $validated['level'],
            'company_id' => isset($validated['company_id']) ? (int) $validated['company_id'] : null,
            'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
            'search' => isset($validated['search']) ? (string) $validated['search'] : null,
            'changed_only' => (bool) ($validated['changed_only'] ?? false),
        ]);

        return response()->json([
            'message' => 'Beállítások sikeresen lekérve.',
            'data' => $data,
        ], Response::HTTP_OK);
    }

    public function save(SaveRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->service->save(
            actorUserId: (int) $request->user()->id,
            context: [
                'level' => (string) $validated['level'],
                'company_id' => isset($validated['company_id']) ? (int) $validated['company_id'] : null,
                'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
            ],
            values: is_array($validated['values'])
                ? SettingSaveValueData::collect($validated['values'])
                : []
        );

        return response()->json([
            'message' => 'Beállítások mentése sikeres.',
            'data' => $result,
        ], Response::HTTP_OK);
    }
}
