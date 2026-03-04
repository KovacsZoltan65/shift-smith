<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkSchedule;

use App\Models\WorkSchedule;
use App\Policies\WorkSchedulePolicy;
use Illuminate\Foundation\Http\FormRequest;

class FetchRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'field' => ['nullable', 'string', 'in:id,name,date_from,date_to,status,created_at'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ];
    }

    public function validatedFilters(): array
    {
        $data = $this->validated();

        return [
            'search' => isset($data['search']) ? trim((string) $data['search']) : null,
            'field' => (string) ($data['field'] ?? 'name'),
            'order' => (string) ($data['order'] ?? 'asc'),
            'page' => (int) ($data['page'] ?? 1),
            'per_page' => (int) ($data['per_page'] ?? 10),
            'company_id' => (int) $data['company_id'],
        ];
    }
}
