<?php

namespace App\Http\Requests\Role;

use App\Models\Admin\Role;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(RolePolicy::PERM_DELETE_ANY, Role::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', "min:1",],
            'ids.*' => ['integer', 'distinct', 'exists:roles,id'],
        ];
    }
}
