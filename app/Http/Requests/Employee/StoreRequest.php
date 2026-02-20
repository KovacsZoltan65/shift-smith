<?php

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use App\Policies\EmployeePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(EmployeePolicy::PERM_CREATE, Employee::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'company_id'  => ['required', 'integer', 'exists:companies,id'],

            'first_name'  => ['required', 'string', 'max:80'],
            'last_name'   => ['required', 'string', 'max:80'],

            'email'       => ['required', 'email', 'max:120'],
            'phone'       => ['nullable', 'string', 'max:50'],
            'position_id' => [
                'nullable',
                'integer',
                Rule::exists('positions', 'id')->where(
                    fn ($q) => $q->where('company_id', (int) $this->input('company_id'))->whereNull('deleted_at')
                ),
            ],

            'hired_at'    => ['nullable', 'date'],
            'active'      => ['nullable', 'boolean'],
        ];
    }
}
