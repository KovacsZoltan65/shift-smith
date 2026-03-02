<?php

declare(strict_types=1);

namespace App\Http\Requests\Absence;

use App\Http\Requests\Absence\Concerns\ResolvesCurrentCompany;
use App\Models\EmployeeAbsence;
use App\Policies\EmployeeAbsencePolicy;
use Illuminate\Foundation\Http\FormRequest;

class ShowAbsenceRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(EmployeeAbsencePolicy::PERM_VIEW_ANY, EmployeeAbsence::class) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
