<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Settings\SettingSaveValueData;
use App\Http\Requests\UpdateLocaleRequest;
use App\Services\Company\CurrentCompanyResolver;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;

/**
 * A fejléc nyelvváltóját a meglévő user settings hierarchiába menti.
 */
final class LocaleController extends Controller
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly CurrentCompanyResolver $currentCompanyResolver,
    ) {
    }

    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = (string) $request->validated('locale');
        $userId = (int) $request->user()->id;
        $companyId = $this->currentCompanyResolver->resolveCompanyId();

        $this->settingsService->save(
            actorUserId: $userId,
            context: [
                'level' => 'user',
                'company_id' => $companyId,
                'user_id' => $userId,
            ],
            values: [
                new SettingSaveValueData(
                    key: 'app.locale',
                    value: $locale,
                ),
            ],
        );

        // A redirectet megelőző aktuális request is ugyanazt a locale-t használja.
        app()->setLocale($locale);

        return redirect()->back(status: 303);
    }
}
