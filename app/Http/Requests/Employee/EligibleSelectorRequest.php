<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class EligibleSelectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return $user->can('work_schedules.autoplan') || $user->can('work_schedules.create');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'eligible_for_autoplan' => ['nullable', 'boolean'],
            'target_daily_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'month' => ['nullable', 'string', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'shift_ids' => ['nullable', 'array'],
            'shift_ids.*' => ['integer', 'exists:work_shifts,id'],
        ];
    }
}
