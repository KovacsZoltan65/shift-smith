<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('companies.update') ?? false;
    }
    
    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rule>>
     */
    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'name'    => [
                'required', 'string', 'max:120',
                Rule::unique('roles', 'name')->ignore($id, 'id')->whereNull('deleted_at'),
            ],
            'guard_name'   => [
                'required', 'string', 'max:120',
                Rule::unique('roles', 'guard_name')->ignore($id, 'id')->whereNull('deleted_at'),
            ],
        ];
    }

}