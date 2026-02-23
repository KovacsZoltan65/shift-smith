<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AutoPlan kompatibilis dolgozó-selector kérés validációja.
 *
 * Az endpoint támogatja a legacy kulcsokat is:
 * - `target_daily_minutes`
 * - `required_daily_minutes`
 * Ezeket egységesen `required_daily_minutes`-re normalizáljuk.
 */
class EligibleSelectorRequest extends FormRequest
{
    /**
     * Csak AutoPlan vagy dolgozó-listázás jogosultsággal érhető el.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return $user->can('work_schedules.autoplan')
            || $user->can('work_schedules.create')
            || $user->can('employees.viewAny');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'eligible_for_autoplan' => ['nullable', 'boolean'],
            'required_daily_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'target_daily_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'month' => ['nullable', 'string', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:120'],
            'shift_ids' => ['nullable', 'array'],
            'shift_ids.*' => ['integer', 'exists:work_shifts,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ha már a preferált kulccsal érkezett, nincs további teendő.
        if ($this->filled('required_daily_minutes')) {
            return;
        }

        // Legacy `target_daily_minutes` kulcs támogatása.
        $target = $this->input('target_daily_minutes');
        if ($target !== null && $target !== '') {
            $this->merge([
                'required_daily_minutes' => $target,
            ]);
        }
    }
}
