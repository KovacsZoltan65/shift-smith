<?php

namespace App\Http\Requests\Permission;

use App\Models\Admin\Permission;
use App\Policies\PermissionPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionPolicy::PERM_UPDATE, Permission::class) ?? false;
    }
    
    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rule>>
     */
    public function rules(): array
    {
        $id = (int) $this->route('id');
        $guard = (string) ($this->input('guard_name') ?: 'web');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('permissions', 'name')
                    ->where('guard_name', $guard)
                    ->ignore($id, 'id'),
            ],
            'guard_name' => ['required', 'string', 'max:50'],
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