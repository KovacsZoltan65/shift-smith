<?php

namespace App\Http\Requests\Permission;

use App\Models\Admin\Permission;
use App\Policies\PermissionPolicy;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionPolicy::PERM_CREATE, Permission::class) ?? false;
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