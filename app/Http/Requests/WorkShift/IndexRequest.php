<?php

namespace App\Http\Requests\WorkShift;

use App\Models\WorkShift;
use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('work_shift.viewAny', WorkShift::class);
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
            'field'    => ['nullable', 'string', 'in:id,name,email,created_at,updated_at'],
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

        if (is_string($order)) {
            $order = strtolower($order);
        }

        $this->merge([
            'field' => $field,
            'order' => $order,
        ]);
    }
}