<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;

final class CurrentCompany
{
    public const SESSION_KEY = 'current_company_id';

    public function currentCompanyId(Request $request): ?int
    {
        $value = $request->session()->get(self::SESSION_KEY);

        if (!is_numeric($value)) {
            return null;
        }

        $companyId = (int) $value;

        return $companyId > 0 ? $companyId : null;
    }

    public function setCurrentCompanyId(Request $request, int $companyId): void
    {
        $request->session()->put(self::SESSION_KEY, $companyId);
    }

    public function clearCurrentCompany(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
