<?php

declare(strict_types=1);

namespace App\Http\Requests\UserAssignments;

use App\Policies\UserAssignmentPolicy;
use Illuminate\Foundation\Http\FormRequest;

final class RemoveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(UserAssignmentPolicy::PERM_UPDATE) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
