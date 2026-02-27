<?php

declare(strict_types=1);

namespace App\Http\Requests\Role;

use App\Policies\RolePolicy;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(RolePolicy::PERM_UPDATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
