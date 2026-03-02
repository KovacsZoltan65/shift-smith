<?php

declare(strict_types=1);

namespace App\Http\Requests\Absence;

use App\Models\EmployeeAbsence;
use App\Policies\EmployeeAbsencePolicy;

class UpdateAbsenceRequest extends StoreAbsenceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(EmployeeAbsencePolicy::PERM_UPDATE, EmployeeAbsence::class) ?? false;
    }
}
