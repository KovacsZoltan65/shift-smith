<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkPattern;

use App\Models\WorkPattern;
use App\Policies\WorkPatternPolicy;
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

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(WorkPatternPolicy::PERM_VIEW_ANY, WorkPattern::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'field' => ['nullable', 'string', 'in:id,name,daily_work_minutes,break_minutes,active,created_at'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ];
    }

    /**
     * @return array{
     *   search?: string|null,
     *   field?: string,
     *   order?: 'asc'|'desc',
     *   page?: int,
     *   per_page?: int,
     *   company_id: int
     * }
     */
    public function validatedFilters(): array
    {
        $data = $this->validated();

        return [
            'search' => isset($data['search']) ? trim((string) $data['search']) : null,
            'field' => (string) ($data['field'] ?? 'id'),
            'order' => (string) ($data['order'] ?? 'desc'),
            'page' => (int) ($data['page'] ?? 1),
            'per_page' => (int) ($data['per_page'] ?? 10),
            'company_id' => (int) $data['company_id'],
        ];
    }
}
