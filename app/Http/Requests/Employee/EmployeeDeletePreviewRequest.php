<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use App\Policies\EmployeePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeDeletePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee
            && ($this->user()?->can(EmployeePolicy::PERM_DELETE, $employee) ?? false);
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'min:1', 'exists:companies,id'],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'strategy' => ['nullable', Rule::in([
                'none',
                'reassign_to_old_supervisor',
                'reassign_to_specific_supervisor',
            ])],
            'target_supervisor_employee_id' => [
                'nullable',
                'integer',
                'min:1',
                'exists:employees,id',
                Rule::requiredIf(fn (): bool => $this->input('strategy', 'none') === 'reassign_to_specific_supervisor'),
            ],
        ];
    }

    /**
     * @return array{
     *   company_id:int,
     *   effective_from:string,
     *   strategy:string,
     *   target_supervisor_employee_id:int|null
     * }
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();

        return [
            'company_id' => (int) $data['company_id'],
            'effective_from' => (string) ($data['effective_from'] ?? now()->toDateString()),
            'strategy' => (string) ($data['strategy'] ?? 'none'),
            'target_supervisor_employee_id' => array_key_exists('target_supervisor_employee_id', $data) && $data['target_supervisor_employee_id'] !== null
                ? (int) $data['target_supervisor_employee_id']
                : null,
        ];
    }
}
