<?php

namespace App\Http\Requests\Role;

use App\Models\Admin\Role;
use App\Models\Company;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(RolePolicy::PERM_CREATE, Role::class) ?? false;
    }
    
    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'guard_name' => ['required', 'string', 'max:50'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }
}