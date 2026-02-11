<?php

namespace App\Http\Requests\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('permissions.create') ?? false;
    }
    
    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rule>>
     */
    public function rules(): array
    {
        $guard = (string) ($this->input('guard_name') ?: 'web');
        
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')->where('guard_name', $guard),
            ],
            'guard_name' => ['required', 'string', 'max:50'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }
    
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name ?? null) ? trim((string) $this->name) : $this->name,
            'guard_name' => is_string($this->guard_name ?? null) ? trim((string) $this->guard_name) : $this->guard_name,
        ]);
    }
}