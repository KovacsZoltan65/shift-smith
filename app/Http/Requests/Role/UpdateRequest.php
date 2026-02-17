<?php

namespace App\Http\Requests\Role;

use App\Models\Admin\Role;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(RolePolicy::PERM_UPDATE, Role::class) ?? false;
    }
    
    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rule>>
     */
    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'name')
                    ->ignore($id, 'id'),
            ],
            'guard_name' => ['required', 'string', 'max:50'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }

}