<?php

declare(strict_types=1);

namespace App\Http\Requests\Absence;

use App\Http\Requests\Absence\Concerns\ResolvesCurrentCompany;
use App\Models\EmployeeAbsence;
use App\Policies\EmployeeAbsencePolicy;
use Illuminate\Foundation\Http\FormRequest;
class StoreAbsenceRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(EmployeeAbsencePolicy::PERM_CREATE, EmployeeAbsence::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'sick_leave_category_id' => ['nullable', 'integer', 'exists:sick_leave_categories,id'],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
