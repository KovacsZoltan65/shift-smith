<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use App\Policies\EmployeePolicy;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateEmployeeLeaveProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(EmployeePolicy::PERM_UPDATE, Employee::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'birth_date' => ['nullable', 'date'],
            'children_count' => ['required', 'integer', 'min:0', 'max:20'],
            'disabled_children_count' => ['required', 'integer', 'min:0', 'max:20'],
            'is_disabled' => ['required', 'boolean'],
        ];
    }
}
