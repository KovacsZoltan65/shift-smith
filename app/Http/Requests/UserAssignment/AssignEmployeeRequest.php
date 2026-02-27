<?php

declare(strict_types=1);

namespace App\Http\Requests\UserAssignment;

use Illuminate\Foundation\Http\FormRequest;

final class AssignEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('user_employees.create') ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ];
    }
}
