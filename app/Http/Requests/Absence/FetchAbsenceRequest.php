<?php

declare(strict_types=1);

namespace App\Http\Requests\Absence;

use App\Http\Requests\Absence\Concerns\ResolvesCurrentCompany;
use App\Models\EmployeeAbsence;
use App\Policies\EmployeeAbsencePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FetchAbsenceRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(EmployeeAbsencePolicy::PERM_VIEW_ANY, EmployeeAbsence::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => [
                'integer',
                Rule::exists('employees', 'id')->where(
                    fn ($query) => $query->where('company_id', $this->currentCompanyId())
                ),
            ],
        ];
    }
}
