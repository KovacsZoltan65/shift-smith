<?php

namespace App\Http\Controllers;

use App\Data\Settings\SettingSaveValueData;
use App\Services\LocaleSettingsService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreferenceController extends Controller
{
    public function __construct(
        private readonly LocaleSettingsService $localeSettings,
        private readonly SettingsService $settingsService,
    ) {}

    public function setLocale(Request $request): Response
    {
        $request->validate(['locale' => $this->localeSettings->validationRule()]);
        $locale = (string) $request->input('locale');

        $this->settingsService->save(
            actorUserId: (int) $request->user()->id,
            context: [
                'level' => 'user',
                'company_id' => null,
                'user_id' => (int) $request->user()->id,
            ],
            values: [
                new SettingSaveValueData(key: LocaleSettingsService::KEY, value: $locale),
            ],
        );

        app()->setLocale($locale);
        $request->setLocale($locale);

        return response()->noContent();
    }

    public function setTimezone(Request $request)
    {
        $request->validate(['timezone' => 'required|string|timezone']);
        $timezone = (string) $request->input('timezone');

        session(['timezone' => $timezone]);

        return response()->noContent();
    }

    public function setTheme(Request $request)
    {
        $request->validate(['theme' => 'required|string|in:light,dark,system']);
        $theme = (string) $request->input('theme');

        session(['theme' => $theme]);
        
        return response()->noContent();
    }
}
