<?php

namespace App\Http\Requests\WorkShift;

use App\Models\WorkShift;
use App\Policies\WorkShiftPolicy;
use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(WorkShiftPolicy::PERM_VIEW, WorkShift::class) ?? false;
    }
    
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search'   => ['nullable', 'string', 'max:255'],
            'field'    => ['nullable', 'string', 'in:id,name,start_time,end_time,work_time_minutes,break_minutes,active,created_at,updated_at'],
            'order'    => ['nullable', 'string', 'in:asc,desc'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
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
     *   search?: string,
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
        $search = \is_string($search) ? trim($search) : null;

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
