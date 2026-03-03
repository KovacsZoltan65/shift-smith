<?php

declare(strict_types=1);

namespace App\Http\Requests\SickLeaveCategory\Concerns;

use App\Support\CurrentCompanyContext;

trait ResolvesCurrentCompany
{
    public function currentCompanyId(): int
    {
        return app(CurrentCompanyContext::class)->resolve($this);
    }
}
