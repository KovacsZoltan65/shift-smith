<?php

namespace App\Http\Requests\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('permissions.update') ?? false;
    }
    
    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rule>>
     */
    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('permissions', 'name')
                    ->ignore($id, 'id'),
            ],
            'guard_name'   => [
                'required', 'string', 'max:120',
                Rule::unique('permissions', 'guard_name')
                    ->ignore($id, 'id'),
            ],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }

}