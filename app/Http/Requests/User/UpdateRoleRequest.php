<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Policies\UserPolicy;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\User;

final class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');
        if (! $target instanceof User) {
            return false;
        }

        return $this->user()?->can(UserPolicy::PERM_ASSIGN_ROLES, $target) ?? false;
    }

    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ];
    }
}
