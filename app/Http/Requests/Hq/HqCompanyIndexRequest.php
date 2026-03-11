<?php

declare(strict_types=1);

namespace App\Http\Requests\Hq;

use App\Policies\HqCompanyPolicy;
use Illuminate\Foundation\Http\FormRequest;

class HqCompanyIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(HqCompanyPolicy::PERM_VIEW) ?? false;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'field' => ['nullable', 'string', 'in:id,name,email,created_at,updated_at'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
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

        if ($field === null && $sortField) {
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
            'search' => $search,
            'field' => $data['field'] ?? 'id',
            'order' => $data['order'] ?? 'desc',
            'page' => (int) ($data['page'] ?? 1),
            'per_page' => (int) ($data['per_page'] ?? 10),
        ];
    }
}
