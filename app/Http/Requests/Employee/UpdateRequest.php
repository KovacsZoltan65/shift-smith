<?php

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use App\Policies\EmployeePolicy;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Controllerben authorizeResource is lesz, de itt is maradhat
        return $this->user()?->can(EmployeePolicy::PERM_UPDATE, Employee::class) ?? false;
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
            'position'    => ['nullable', 'string', 'max:120'],

            'hired_at'    => ['nullable', 'date'],
            'active'      => ['nullable', 'boolean'],
        ];
    }
}
