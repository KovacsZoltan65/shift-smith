<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Company;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CurrentCompanyContext
{
    public function resolve(Request $request): int
    {
        $companyId = (int) $request->session()->get('current_company_id', 0);

        if ($companyId > 0 && Company::query()->whereKey($companyId)->exists()) {
            return $companyId;
        }

        $fallbackId = (int) (Company::query()->active()->orderBy('id')->value('id')
            ?? Company::query()->orderBy('id')->value('id')
            ?? 0);

        if ($fallbackId <= 0) {
            throw new HttpException(422, 'Nincs aktív cég kontextus.');
        }

        $request->session()->put('current_company_id', $fallbackId);

        return $fallbackId;
    }
}
