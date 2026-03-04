<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkSchedule;

use App\Models\WorkSchedule;
use App\Policies\WorkSchedulePolicy;
use Illuminate\Foundation\Http\FormRequest;

class SelectorRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('company_id')) {
            return;
        }

        $companyId = $this->session()->get('current_company_id');
        if ($companyId !== null) {
            $this->merge(['company_id' => (int) $companyId]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can(WorkSchedulePolicy::PERM_VIEW_ANY, WorkSchedule::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'only_published' => ['nullable', 'boolean'],
        ];
    }
}
