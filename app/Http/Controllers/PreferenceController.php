<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreferenceController extends Controller
{
    public function setLocale(Request $request): Response
    {
        $request->validate(['locale' => 'required|string|in:en,hu']);
        $locale = (string) $request->input('locale');

        session(['locale' => $locale]);
        app()->setLocale($locale);

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
