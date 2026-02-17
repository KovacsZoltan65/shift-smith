<?php

namespace App\Http\Requests\Role;

use App\Models\Admin\Role;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(RolePolicy::PERM_DELETE, Role::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id'   => ['required', 'integer'],
        ];
    }
}
