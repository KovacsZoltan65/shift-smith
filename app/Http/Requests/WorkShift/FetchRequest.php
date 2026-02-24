<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkShift;

use App\Models\WorkShift;
use App\Policies\WorkShiftPolicy;
use Illuminate\Foundation\Http\FormRequest;

class FetchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(WorkShiftPolicy::PERM_VIEW_ANY, WorkShift::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'field' => ['nullable', 'string', 'in:id,name,start_time,end_time,work_time_minutes,break_minutes,active,created_at,updated_at'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'active' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $field = $this->input('field');
        $order = $this->input('order');

        if ($field === '') {
            $field = null;
        }
        if ($order === '') {
            $order = null;
        }

        $sortField = $this->input('sortField');
        $sortOrder = $this->input('sortOrder');

        if ($field === null && is_string($sortField) && $sortField !== '') {
            $field = $sortField;
        }

        if ($order === null && $sortOrder !== null) {
            if ($sortOrder === 1 || $sortOrder === '1') {
                $order = 'asc';
            }
            if ($sortOrder === -1 || $sortOrder === '-1') {
                $order = 'desc';
            }
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
     *   search?: ?string,
     *   field?: string,
     *   order?: 'asc'|'desc',
     *   active?: ?bool,
     *   page?: int,
     *   per_page?: int
     * }
     */
    public function validatedFilters(): array
    {
        $data = $this->validated();
        $search = isset($data['search']) ? trim((string) $data['search']) : null;

        if ($search === '' || $search === 'null' || $search === 'undefined') {
            $search = null;
        }

        return [
            'search' => $search,
            'field' => (string) ($data['field'] ?? 'id'),
            'order' => (string) ($data['order'] ?? 'desc'),
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : null,
            'page' => (int) ($data['page'] ?? 1),
            'per_page' => (int) ($data['per_page'] ?? 10),
        ];
    }
}
