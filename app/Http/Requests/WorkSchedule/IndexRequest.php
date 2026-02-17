<?php

namespace App\Http\Requests\WorkSchedule;

use App\Models\WorkSchedule;
use App\Policies\WorkSchedulePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(WorkSchedulePolicy::PERM_VIEW_ANY, WorkSchedule::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],

            'search' => ['nullable', 'string', 'max:200'],

            'field' => ['nullable', 'string', Rule::in(WorkSchedule::SORTABLE)],
            'order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],

            'company_id' => ['nullable', 'integer', 'exists:companies,id'],

            'status' => ['nullable', 'string', Rule::in(['draft', 'published'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $field = $this->input('field');
        $order = $this->input('order');

        if ($field === '') $field = null;
        if ($order === '') $order = null;

        $sortField = $this->input('sortField');
        $sortOrder = $this->input('sortOrder');

        if ($field === null && $sortField) {
            $field = $sortField;
        }

        if ($order === null && $sortOrder !== null) {
            if ($sortOrder === 1 || $sortOrder === '1') $order = 'asc';
            if ($sortOrder === -1 || $sortOrder === '-1') $order = 'desc';
        }

        if (\is_string($order)) {
            $order = strtolower($order);
        }

        $this->merge([
            'field' => $field,
            'order' => $order,
        ]);
    }

    /**
     * @return array{
     *   search?: string|null,
     *   company_id?: int|null,
     *   status?: 'draft'|'published'|null,
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   field?: string|null,
     *   order?: 'asc'|'desc'|null,
     *   page: int,
     *   per_page: int
     * }
     */
    public function validatedFilters(): array
    {
        $data = $this->validated();

        $search = $data['search'] ?? null;
        $search = \is_string($search) ? trim($search) : null;
        if ($search === '' || $search === 'null' || $search === 'undefined') {
            $search = null;
        }

        return [
            'search'     => $search,
            'company_id' => isset($data['company_id']) ? (int) $data['company_id'] : null,
            'status'     => $data['status'] ?? null,
            'date_from'  => $data['date_from'] ?? null,
            'date_to'    => $data['date_to'] ?? null,
            'field'      => $data['field'] ?? null,
            'order'      => $data['order'] ?? null,
            'page'       => isset($data['page']) ? (int) $data['page'] : 1,
            'per_page'   => isset($data['per_page']) ? (int) $data['per_page'] : 10,
        ];
    }
}
