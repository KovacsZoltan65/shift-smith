<?php

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use App\Policies\EmployeePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(EmployeePolicy::PERM_VIEW_ANY) ?? false;
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

            'field' => ['nullable', 'string', Rule::in(Employee::SORTABLE)],
            'order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],

            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ];
    }
    
    protected function prepareForValidation(): void
    {
        $field = $this->input('field');
        $order = $this->input('order');

        // üres string -> null (különben az "in:" elhasal)
        if ($field === '') $field = null;
        if ($order === '') $order = null;

        // (opcionális) PrimeVue támogatás:
        $sortField = $this->input('sortField');
        $sortOrder = $this->input('sortOrder');

        if ($field === null && $sortField) {
            $field = $sortField;
        }

        if ($order === null && $sortOrder !== null) {
            if ($sortOrder === 1 || $sortOrder === '1') $order = 'asc';
            if ($sortOrder === -1 || $sortOrder === '-1') $order = 'desc';
        }

        if (is_string($order)) {
            $order = strtolower($order);
        }

        $this->merge([
            'field' => $field,
            'order' => $order,
        ]);
    }
    
    /**
     * @return array{
     *   search?: string,
     *   name?: string,
     *   email?: string,
     *   phone?: string,
     *   field?: string,
     *   order?: 'asc'|'desc',
     *   page?: int,
     *   per_page?: int
     * }
     */
    public function validatedFilters(): array
    {
        $data = $this->validated();

        $search = $data['search'] ?? null;
        $search = is_string($search) ? trim($search) : null;

        if ($search === '' || $search === 'null' || $search === 'undefined') {
            $search = null;
        }

        return [
            'search'   => $search,
            'field'    => $data['field'] ?? 'id',
            'order'    => $data['order'] ?? 'desc',
            'page'     => (int) ($data['page'] ?? 1),
            'per_page' => (int) ($data['per_page'] ?? 10),
        ];
    }
}
